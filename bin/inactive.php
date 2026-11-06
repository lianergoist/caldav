<?php
declare(strict_types=1);

/*
 * Sletter konti, der ikke har været brugt i inactive_days dage. Køres dagligt som root fra
 * /etc/cron.d/caldav-inactive (root, fordi Radicales data ejes af brugeren radicale).
 *
 *   php bin/inactive.php            normal kørsel (respekterer inactive_dry_run i config.php)
 *   php bin/inactive.php --dry-run  vis kun, hvad der ville ske – sender og sletter intet
 *   php bin/inactive.php --live     kør rigtigt, selv om inactive_dry_run er sat i config.php
 *
 * Gang i gang:
 *  1. Læs "Successful login: '<bruger>'" fra Radicales journal siden sidste kørsel og opdatér last_seen.
 *     Portalen opdaterer selv last_seen ved login og oprettelse.
 *  2. Konti uden registreret aktivitet får last_seen = nu (de får altså hele fristen fra første kørsel).
 *  3. Advarsel på mail inactive_warn_days før sletning. Sletning sker tidligst inactive_warn_days
 *     efter advarslen, også selv om scriptet har været nede.
 *  4. Sletning: fjern fra htpasswd, flyt data til en karantænemappe (inactive_keep_days), mail brugeren.
 *
 * Sikringer: undtagelsesliste, nødstop hvis journalen ikke kan læses eller ikke har vist et eneste
 * login i 3 døgn, og stop hvis for mange konti ville blive slettet på én gang.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../lib.php';

const SECONDS_PER_DAY = 86400;
const HEARTBEAT_MAX_AGE = 3 * SECONDS_PER_DAY;   // nødstop, hvis journalen ikke har vist et login i så lang tid

date_default_timezone_set('Europe/Copenhagen');
$dryRun = in_array('--dry-run', $argv, true)
    || (!in_array('--live', $argv, true) && (bool)($CONFIG['inactive_dry_run'] ?? true));
$inactiveDays = (int)($CONFIG['inactive_days'] ?? 0);
$warnDays = max(1, (int)($CONFIG['inactive_warn_days'] ?? 7));
$keepDays = max(0, (int)($CONFIG['inactive_keep_days'] ?? 7));
$exemptEmails = array_map('strtolower', (array)($CONFIG['inactive_exempt'] ?? []));
$storage = rtrim((string)($CONFIG['radicale_storage'] ?? '/var/lib/radicale/collections'), '/');
$adminEmail = (string)($CONFIG['contact_email'] ?? '');
$now = time();
$log = [];   // alle linjer fra out(), sendes til admin ved problemer

/** Skriv en linje til konsollen og gem den i $log. */
function out(string $line): void
{
    global $log;
    $log[] = $line;
    echo $line, "\n";
}

/** Portalens filer skal blive ved med at tilhøre www-data, selv om vi kører som root. */
register_shutdown_function(function (): void {
    if (posix_geteuid() !== 0) {
        return;
    }
    foreach (glob(dirname((string)cfg('db_file')) . '/*') ?: [] as $dataFile) {
        @chown($dataFile, 'www-data');
        @chgrp($dataFile, 'www-data');
    }
});

function admin_mail(string $subject, string $body): void
{
    global $adminEmail;
    if ($adminEmail !== '') {
        send_mail($adminEmail, '[caldav-inactive] ' . $subject, $body);
    }
}

/** Læs en værdi fra meta-tabellen (fx last_scan). */
function meta_get(string $key): ?string
{
    $query = db()->prepare('SELECT v FROM meta WHERE k = ?');
    $query->execute([$key]);
    $value = $query->fetchColumn();
    return $value === false ? null : (string)$value;
}

function meta_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO meta (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')
        ->execute([$key, $value]);
}

function format_date(int $timestamp, string $lang): string
{
    return $lang === 'en' ? date('j F Y', $timestamp) : date('d-m-Y', $timestamp);
}

/** Flytter brugerens Radicale-mappe til karantæne under Radicales storage-lås. */
function quarantine_data(string $user, string $storage, int $now): bool
{
    $collectionRoot = $storage . '/collection-root';
    if ($user === '' || $user === '.' || $user === '..' || strpbrk($user, "/\\\0") !== false) {
        return false;
    }
    $userDir = $collectionRoot . '/' . $user;
    if (!file_exists($userDir)) {
        return true; // ingen data oprettet
    }
    $userDirReal = realpath($userDir);
    if ($userDirReal === false || dirname($userDirReal) !== realpath($collectionRoot) || !is_dir($userDirReal) || is_link($userDir)) {
        return false;
    }
    $quarantineDir = dirname($storage) . '/deleted';
    if (!is_dir($quarantineDir) && !mkdir($quarantineDir, 0700)) {
        return false;
    }
    $lockFile = fopen($storage . '/.Radicale.lock', 'c');
    if ($lockFile === false || !flock($lockFile, LOCK_EX)) {
        return false;
    }
    try {
        return rename($userDirReal, $quarantineDir . '/' . date('Ymd-His', $now) . '_' . rawurlencode($user));
    } finally {
        flock($lockFile, LOCK_UN);
        fclose($lockFile);
    }
}

/** Slet en mappe og alt indhold (rekursivt). */
function remove_dir_recursive(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                remove_dir_recursive($path . '/' . $entry);
            }
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}

out(sprintf('Start %s%s, frist %d dage, advarsel %d dage før.', date('Y-m-d H:i', $now),
    $dryRun ? ' (PRØVEKØRSEL)' : '', $inactiveDays, $warnDays));

/* ---------- 1. læs logins fra journalen ---------- */

$since = (int)(meta_get('last_scan') ?? ($now - 100 * SECONDS_PER_DAY));
$journalCommand = sprintf('journalctl -u radicale --since @%d --until @%d -o short-unix --no-pager -q 2>&1',
    $since, $now);
exec($journalCommand, $journalLines, $exitCode);
if ($exitCode !== 0) {
    out("FEJL: journalctl gav kode $exitCode. Intet ændret.");
    admin_mail('journalctl fejlede', implode("\n", array_merge($log, array_slice($journalLines, 0, 20))));
    exit(1);
}
$lastLoginByUser = [];
foreach ($journalLines as $line) {
    if (preg_match("/^(\\d+)(?:\\.\\d+)? .*Successful login: '(.+)' \\(/", $line, $match)) {
        $lastLoginByUser[$match[2]] = max($lastLoginByUser[$match[2]] ?? 0, (int)$match[1]);
    }
}
$users = htpasswd_load();
$updateActivity = db()->prepare('UPDATE activity SET last_seen = MAX(last_seen, :login_time),
    warned_at = CASE WHEN warned_at IS NOT NULL AND warned_at < :login_time THEN NULL ELSE warned_at END
    WHERE email = :email');
$insertActivity = db()->prepare('INSERT OR IGNORE INTO activity (email, last_seen) VALUES (?, ?)');
foreach ($lastLoginByUser as $email => $loginTime) {
    if (isset($users[$email])) {
        $insertActivity->execute([$email, $loginTime]);
        $updateActivity->execute([':login_time' => $loginTime, ':email' => $email]);
    }
}
if ($lastLoginByUser) {
    meta_set('last_login_seen', (string)max((int)(meta_get('last_login_seen') ?? 0), max($lastLoginByUser)));
}
meta_set('last_scan', (string)$now); // også ved prøvekørsel: det er kun registrering af aktivitet
out(sprintf('Journal: logins fra %d brugere siden %s.', count($lastLoginByUser), date('Y-m-d H:i', $since)));

/* ---------- 2. synkronisér med htpasswd ---------- */

foreach ($users as $email => $_) {
    $insertActivity->execute([$email, $now]); // ny for scriptet: fristen starter nu
}
$activityRows = db()->query('SELECT email, last_seen, warned_at, lang FROM activity')->fetchAll(PDO::FETCH_ASSOC);
foreach ($activityRows as $row) {
    if (!isset($users[$row['email']])) {
        activity_forget($row['email']); // slettet på anden vis
    }
}

if ($inactiveDays <= 0) {
    out('inactive_days er 0: kun aktivitet registreret, ingen advarsler eller sletning.');
    exit(0);
}

/* ---------- nødstop ---------- */

$heartbeat = (int)(meta_get('last_login_seen') ?? 0);
if ($now - $heartbeat > HEARTBEAT_MAX_AGE) {
    out('NØDSTOP: ingen logins i journalen i over 3 døgn. Radicale eller logningen virker måske ikke. '
        . 'Ingen advarsler eller sletninger.');
    admin_mail('nødstop', implode("\n", $log));
    exit(1);
}

/* ---------- 3. advarsler og sletninger ---------- */

$accounts = db()->query('SELECT email, last_seen, warned_at, lang FROM activity')->fetchAll(PDO::FETCH_ASSOC);
$toWarn = [];
$toDelete = [];
foreach ($accounts as $account) {
    $email = $account['email'];
    if (in_array(strtolower($email), $exemptEmails, true)) {
        continue;
    }
    $idleSeconds = $now - (int)$account['last_seen'];
    $warnedAt = $account['warned_at'] !== null ? (int)$account['warned_at'] : null;
    if ($idleSeconds >= $inactiveDays * SECONDS_PER_DAY && $warnedAt !== null && $now - $warnedAt >= $warnDays * SECONDS_PER_DAY - 3600) {
        $toDelete[] = $account;
    } elseif ($idleSeconds >= ($inactiveDays - $warnDays) * SECONDS_PER_DAY && $warnedAt === null) {
        $toWarn[] = $account;
    }
}

$maxDelete = max(3, intdiv(count($users), 5));
if (count($toDelete) > $maxDelete) {
    out(sprintf('STOP: %d konti ville blive slettet på én gang (grænse %d). Ingen sletninger. '
        . 'Kør med --dry-run for at se listen, og tjek at aktiviteten registreres rigtigt.',
        count($toDelete), $maxDelete));
    foreach ($toDelete as $account) {
        out('  ' . $account['email'] . ' sidst brugt ' . date('Y-m-d', (int)$account['last_seen']));
    }
    admin_mail('for mange sletninger', implode("\n", $log));
    $toDelete = [];
}

foreach ($toWarn as $account) {
    $email = $account['email'];
    $lang = $account['lang'] === 'en' ? 'en' : 'da';
    $deleteAt = max((int)$account['last_seen'] + $inactiveDays * SECONDS_PER_DAY, $now + $warnDays * SECONDS_PER_DAY);
    out(sprintf('%s: %s (sidst brugt %s, slettes tidligst %s)', $dryRun ? 'Ville advare' : 'Advarer',
        $email, date('Y-m-d', (int)$account['last_seen']), date('Y-m-d', $deleteAt)));
    if ($dryRun) {
        continue;
    }
    if (normalize_email($email) === null) {
        out("  $email er ikke en mailadresse – ingen mail sendt, og kontoen slettes ikke automatisk.");
        continue;
    }
    if (send_mail($email, t_for_lang($lang, 'inact_warn_subject'), t_for_lang($lang, 'inact_warn_body', $email,
        format_date((int)$account['last_seen'], $lang), format_date($deleteAt, $lang),
        cfg('base_url') . '/login.php?lang=' . $lang))) {
        db()->prepare('UPDATE activity SET warned_at = ? WHERE email = ?')->execute([$now, $email]);
    } else {
        out("  Mail til $email kunne ikke sendes – prøver igen i morgen.");
    }
}

$deleted = [];
foreach ($toDelete as $account) {
    $email = $account['email'];
    $lang = $account['lang'] === 'en' ? 'en' : 'da';
    out(sprintf('%s: %s (sidst brugt %s)', $dryRun ? 'Ville slette' : 'Sletter', $email,
        date('Y-m-d', (int)$account['last_seen'])));
    if ($dryRun) {
        continue;
    }
    if (!user_remove($email)) {
        out("  FEJL: kunne ikke fjerne $email fra htpasswd. Springes over.");
        continue;
    }
    if (!quarantine_data($email, $storage, $now)) {
        out("  FEJL: $email er fjernet fra htpasswd, men data kunne ikke flyttes. Ryd op manuelt i $storage/collection-root.");
    }
    activity_forget($email);
    db()->prepare('DELETE FROM tokens WHERE email = ?')->execute([$email]);
    send_mail($email, t_for_lang($lang, 'inact_deleted_subject'),
        t_for_lang($lang, 'inact_deleted_body', $email, $inactiveDays, cfg('base_url') . '/register.php?lang=' . $lang));
    $deleted[] = $email;
}

/* ---------- 4. ryd karantæne ---------- */

$quarantineDir = dirname($storage) . '/deleted';
if (!$dryRun && is_dir($quarantineDir)) {
    foreach (scandir($quarantineDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $quarantinedPath = $quarantineDir . '/' . $entry;
        $movedAt = @filectime($quarantinedPath); // ctime = tidspunktet for flytningen til karantæne
        if ($movedAt !== false && $now - $movedAt > $keepDays * SECONDS_PER_DAY) {
            remove_dir_recursive($quarantinedPath);
            out("Karantæne ryddet: $entry");
        }
    }
}

/* ---------- 5. venteliste: ledige pladser går til de næste i køen, udløbne invitationer ryddes ---------- */

if (!$dryRun) {
    $invitesSent = waitlist_send_invites();
    if ($invitesSent > 0) {
        out("Venteliste: $invitesSent invitation(er) sendt.");
    }
}

out(sprintf('Færdig: %d advaret, %d slettet.', $dryRun ? 0 : count($toWarn), count($deleted)));
if ($deleted) {
    admin_mail(count($deleted) . ' konto(er) slettet', implode("\n", $log));
}
