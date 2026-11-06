<?php
declare(strict_types=1);

$CONFIG = require __DIR__ . '/config.php';
require __DIR__ . '/lang.php';

/** Hent en værdi fra config.php. */
function cfg(string $key)
{
    global $CONFIG;
    return $CONFIG[$key];
}

/** HTML-escape en tekst, så den kan sættes sikkert ind i HTML. */
function h(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- session / csrf ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('caldavportal');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): bool
{
    start_session();
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function current_user(): ?string
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function do_logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookieParams = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $cookieParams['path'], $cookieParams['domain'] ?? '', true, true);
    }
    session_destroy();
}

/** Returnerer den indloggede brugers e-mail, eller sender til login.php og stopper. */
function require_login(): string
{
    $email = current_user();
    if ($email === null || !array_key_exists($email, htpasswd_load())) {
        if ($email !== null) {
            do_logout();
        }
        header('Location: login.php');
        exit;
    }
    return $email;
}

/* ---------- database / rate limiting / tokens ---------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . cfg('db_file'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS tokens (
        hash TEXT PRIMARY KEY, purpose TEXT NOT NULL, email TEXT NOT NULL,
        pw_hash TEXT, expires INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS attempts (k TEXT NOT NULL, ts INTEGER NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS attempts_k ON attempts(k, ts)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS activity (
        email TEXT PRIMARY KEY, last_seen INTEGER NOT NULL, warned_at INTEGER, lang TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS meta (k TEXT PRIMARY KEY, v TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS waitlist (
        email TEXT PRIMARY KEY, joined INTEGER NOT NULL, lang TEXT, leave_hash TEXT, invited INTEGER)');
    $now = time();
    $pdo->prepare('DELETE FROM tokens WHERE expires < ?')->execute([$now]);
    $pdo->prepare('DELETE FROM attempts WHERE ts < ?')->execute([$now - 86400]);
    return $pdo;
}

/** Tæller et forsøg og returnerer true, hvis der allerede er $maxAttempts forsøg inden for $windowSeconds. */
function rate_limited(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $query = db()->prepare('SELECT COUNT(*) FROM attempts WHERE k = ? AND ts > ?');
    $query->execute([$key, time() - $windowSeconds]);
    if ((int)$query->fetchColumn() >= $maxAttempts) {
        return true;
    }
    db()->prepare('INSERT INTO attempts (k, ts) VALUES (?, ?)')->execute([$key, time()]);
    return false;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Maks. 3 mails pr. adresse i timen (forhindrer mail-bombning af andre). */
function mail_allowed(string $email): bool
{
    return !rate_limited('mail:' . $email, 3, 3600);
}

/**
 * Opretter et engangstoken (fx til bekræftelse eller nulstilling) og returnerer det.
 * Kun en hash af tokenet gemmes i databasen. Et tidligere token med samme formål slettes.
 */
function token_create(string $purpose, string $email, ?string $passwordHash, int $lifetimeSeconds): string
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('DELETE FROM tokens WHERE purpose = ? AND email = ?')->execute([$purpose, $email]);
    db()->prepare('INSERT INTO tokens (hash, purpose, email, pw_hash, expires) VALUES (?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $purpose, $email, $passwordHash, time() + $lifetimeSeconds]);
    return $token;
}

/** Finder et gyldigt, ikke-udløbet token. Returnerer rækken fra databasen eller null. */
function token_get(string $purpose, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $query = db()->prepare('SELECT * FROM tokens WHERE hash = ? AND purpose = ? AND expires > ?');
    $query->execute([hash('sha256', $token), $purpose, time()]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function token_delete(string $token): void
{
    db()->prepare('DELETE FROM tokens WHERE hash = ?')->execute([hash('sha256', $token)]);
}

/* ---------- aktivitet (til sletning af inaktive konti, se bin/inactive.php) ---------- */

/** Markér kontoen som brugt nu (portal-login, oprettelse). Nulstiller en evt. advarsel. */
function activity_touch(string $email, ?string $lang = null): void
{
    db()->prepare('INSERT INTO activity (email, last_seen, warned_at, lang) VALUES (?, ?, NULL, ?)
        ON CONFLICT(email) DO UPDATE SET last_seen = excluded.last_seen, warned_at = NULL,
        lang = COALESCE(excluded.lang, activity.lang)')
        ->execute([$email, time(), $lang]);
}

function activity_forget(string $email): void
{
    db()->prepare('DELETE FROM activity WHERE email = ?')->execute([$email]);
}

/* ---------- pladser og venteliste ---------- */

/** Hvor længe en invitation fra ventelisten holder pladsen, i sekunder. */
function waitlist_invite_ttl(): int
{
    return max(1, (int)(cfg_or('waitlist_invite_days', 7))) * 86400;
}

/** Værdi fra config med en standardværdi. */
function cfg_or(string $key, $default)
{
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

/** Antal pladser, der er lovet væk til ventelisten: folk der venter, og invitationer der ikke er udløbet. */
function waitlist_reserved(): int
{
    $query = db()->prepare('SELECT COUNT(*) FROM waitlist WHERE invited IS NULL OR invited > ?');
    $query->execute([time() - waitlist_invite_ttl()]);
    return (int)$query->fetchColumn();
}

/** Er der en ledig plads til en ny tilmelding, der ikke kommer fra ventelisten? */
function registration_open(): bool
{
    return count(htpasswd_load()) + waitlist_reserved() < (int)cfg('max_users');
}

/** Opretter et afmeldingslink til ventelisten og gemmer kun dets hash. */
function waitlist_new_leave_token(string $email): string
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE waitlist SET leave_hash = ? WHERE email = ?')->execute([hash('sha256', $token), $email]);
    return $token;
}

/**
 * Skriver en (bekræftet) adresse på ventelisten.
 * @return array{0: string, 1: int} ['added'|'exists', nummer i køen]
 */
function waitlist_add(string $email, string $lang): array
{
    $insert = db()->prepare('INSERT OR IGNORE INTO waitlist (email, joined, lang) VALUES (?, ?, ?)');
    $insert->execute([$email, time(), $lang]);
    $status = $insert->rowCount() > 0 ? 'added' : 'exists';
    // Nummer i køen: dem uden invitation, der kom før (rowid afgør, hvis tidspunktet er det samme).
    $query = db()->prepare('SELECT COUNT(*) FROM waitlist AS other, waitlist AS me WHERE me.email = ?
        AND other.invited IS NULL AND (other.joined < me.joined OR (other.joined = me.joined AND other.rowid <= me.rowid))');
    $query->execute([$email]);
    return [$status, max(1, (int)$query->fetchColumn())];
}

function waitlist_remove(string $email): void
{
    db()->prepare('DELETE FROM waitlist WHERE email = ?')->execute([$email]);
    db()->prepare("DELETE FROM tokens WHERE purpose = 'invite' AND email = ?")->execute([$email]);
}

/** Fjerner adressen via afmeldingslinket. Returnerer true, hvis linket var gyldigt. */
function waitlist_leave(string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }
    $query = db()->prepare('SELECT email FROM waitlist WHERE leave_hash = ?');
    $query->execute([hash('sha256', $token)]);
    $email = $query->fetchColumn();
    if ($email === false) {
        return false;
    }
    waitlist_remove((string)$email);
    return true;
}

/**
 * Rydder op i ventelisten og inviterer de næste i køen, hvis der er ledige pladser.
 * Kaldes fra cron (bin/waitlist.php), når nogen kommer på listen, og når en konto slettes.
 * @return int antal invitationer sendt
 */
function waitlist_send_invites(): int
{
    $pdo = db();
    $now = time();
    $ttl = waitlist_invite_ttl();
    $users = htpasswd_load();
    $pdo->exec('BEGIN IMMEDIATE'); // én ad gangen, så den samme plads ikke gives væk to gange
    try {
        // Udløbne invitationer: pladsen går videre til den næste.
        $pdo->prepare('DELETE FROM waitlist WHERE invited IS NOT NULL AND invited <= ?')->execute([$now - $ttl]);
        // Folk, der har fået en konto ad anden vej, står ikke længere i kø.
        foreach ($pdo->query('SELECT email FROM waitlist')->fetchAll(PDO::FETCH_COLUMN) as $email) {
            if (isset($users[$email])) {
                $pdo->prepare('DELETE FROM waitlist WHERE email = ?')->execute([$email]);
            }
        }
        $invitedCount = (int)$pdo->query('SELECT COUNT(*) FROM waitlist WHERE invited IS NOT NULL')->fetchColumn();
        $freeSeats = (int)cfg('max_users') - count($users) - $invitedCount;
        if ($freeSeats <= 0) {
            $pdo->exec('COMMIT');
            return 0;
        }
        $next = $pdo->prepare('SELECT email, lang FROM waitlist WHERE invited IS NULL ORDER BY joined, rowid LIMIT ?');
        $next->execute([$freeSeats]);
        $toInvite = $next->fetchAll(PDO::FETCH_ASSOC);
        $mails = [];
        foreach ($toInvite as $row) {
            $email = (string)$row['email'];
            $lang = isset(LANGS[$row['lang'] ?? '']) ? (string)$row['lang'] : 'da';
            $pdo->prepare('UPDATE waitlist SET invited = ? WHERE email = ?')->execute([$now, $email]);
            $inviteToken = token_create('invite', $email, null, $ttl);
            $leaveToken = waitlist_new_leave_token($email);
            $mails[] = [$email, t_for_lang($lang, 'mail_invite_subject'), t_for_lang($lang, 'mail_invite_body',
                intdiv($ttl, 86400),
                cfg('base_url') . "/register.php?invite=$inviteToken&lang=$lang",
                cfg('base_url') . "/waitlist.php?leave=$leaveToken&lang=$lang")];
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
    foreach ($mails as [$to, $subject, $body]) {
        send_mail($to, $subject, $body);
    }
    return count($mails);
}

/* ---------- validering ---------- */

/** Returnerer adressen med små bogstaver og uden mellemrum, eller null hvis den ikke er gyldig. */
function normalize_email(string $email): ?string
{
    $email = strtolower(trim($email));
    if (strlen($email) > 254
        || !preg_match('/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/', $email)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $email;
}

/** Returnerer en fejlbesked, hvis adgangskoden er for kort eller for lang, ellers null. */
function validate_password(string $password): ?string
{
    $minLength = (int)cfg('min_password');
    if (strlen($password) < $minLength) {
        return t('pw_short', $minLength);
    }
    if (strlen($password) > 72) {
        return t('pw_long');
    }
    return null;
}

/** Bcrypt-hash i det format, Radicale forstår. */
function make_hash(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]); // $2y$ — læses af Radicale
}

/* ---------- htpasswd ---------- */

/** @return array<string, string> e-mail => password-hash for alle brugere i htpasswd-filen */
function htpasswd_load(): array
{
    $htpasswdFile = cfg('htpasswd_file');
    $users = [];
    if (!is_file($htpasswdFile)) {
        return $users;
    }
    foreach (file($htpasswdFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $colonPos = strpos($line, ':');
        if ($colonPos === false) {
            continue;
        }
        $users[substr($line, 0, $colonPos)] = substr($line, $colonPos + 1);
    }
    return $users;
}

/**
 * Læs-ændr-skriv under lås. $changeUsers får brugerlisten og returnerer den nye liste
 * (eller null for at afbryde). Skriver atomisk via temp-fil + rename.
 */
function htpasswd_update(callable $changeUsers): bool
{
    $htpasswdFile = cfg('htpasswd_file');
    $lockFile = fopen(dirname((string)cfg('db_file')) . '/htpasswd.lock', 'c');
    if ($lockFile === false) {
        return false;
    }
    flock($lockFile, LOCK_EX);
    try {
        $newUsers = $changeUsers(htpasswd_load());
        if ($newUsers === null) {
            return false;
        }
        $fileContent = '';
        foreach ($newUsers as $email => $hash) {
            $fileContent .= $email . ':' . $hash . "\n";
        }
        $tempFile = tempnam(dirname($htpasswdFile), '.htp');
        if ($tempFile === false) {
            return false;
        }
        chmod($tempFile, 0640);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            chown($tempFile, 'www-data'); // cron kører som root; portalen (www-data) skal stadig kunne skrive
        }
        if (file_put_contents($tempFile, $fileContent) === false || !rename($tempFile, $htpasswdFile)) {
            @unlink($tempFile);
            return false;
        }
        return true;
    } finally {
        flock($lockFile, LOCK_UN);
        fclose($lockFile);
    }
}

/**
 * Opretter en bruger. $fromWaitlist = true for en invitation fra ventelisten: den har allerede en
 * plads holdt til sig. Andre tilmeldinger må ikke tage pladser, der er lovet væk til ventelisten.
 * @return string 'ok' | 'exists' | 'full' | 'error'
 */
function user_create(string $email, string $hash, bool $fromWaitlist = false): string
{
    $result = 'error';
    $saved = htpasswd_update(function (array $users) use ($email, $hash, $fromWaitlist, &$result) {
        if (isset($users[$email])) {
            $result = 'exists';
            return null;
        }
        $reserved = $fromWaitlist ? 0 : waitlist_reserved();
        if (count($users) + $reserved >= (int)cfg('max_users')) {
            $result = 'full';
            return null;
        }
        $users[$email] = $hash;
        $result = 'ok';
        return $users;
    });
    return $saved ? 'ok' : ($result === 'ok' ? 'error' : $result);
}

function user_set_hash(string $email, string $hash): bool
{
    return htpasswd_update(function (array $users) use ($email, $hash) {
        if (!isset($users[$email])) {
            return null;
        }
        $users[$email] = $hash;
        return $users;
    });
}

function user_remove(string $email): bool
{
    return htpasswd_update(function (array $users) use ($email) {
        if (!isset($users[$email])) {
            return null;
        }
        unset($users[$email]);
        return $users;
    });
}

function user_verify(string $email, string $password): bool
{
    $hash = htpasswd_load()[$email] ?? null;
    if ($hash === null) {
        password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]); // jævn svartid
        return false;
    }
    return password_verify($password, $hash);
}

/* ---------- Radicale ---------- */

/** Sletter brugerens samlinger via CalDAV (HTTP DELETE på /<bruger>/) med brugerens egen login. */
function radicale_delete_data(string $user, string $password): bool
{
    $url = rtrim((string)cfg('radicale_internal_url'), '/') . '/' . rawurlencode($user) . '/';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_USERPWD        => $user . ':' . $password,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    curl_exec($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    return in_array($httpStatus, [200, 204, 404], true); // 404 = ingen data oprettet endnu
}

/* ---------- mail ---------- */

function send_mail(string $to, string $subject, string $body): bool
{
    $from = (string)cfg('from_email');
    $headers = [
        'From: =?UTF-8?B?' . base64_encode((string)cfg('from_name')) . '?= <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Auto-Submitted: auto-generated',
    ];
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $from);
}

/* ---------- layout ---------- */

function page_head(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    $user = current_user();
    $currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    echo '<!doctype html><html lang="' . h(lang()) . '"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="color-scheme" content="light dark">'
       . '<title>' . h($title) . ' – ' . th('site_name') . '</title>'
       . '<link rel="stylesheet" href="style.css"></head><body>'
       . '<header class="masthead"><div class="masthead__inner">'
       . '<a class="masthead__title" href="index.php">caldav<span>.rpix.dk</span></a>'
       . '<nav class="masthead__nav">';
    if ($user !== null) {
        if ($currentPage !== 'account.php') {
            echo '<a href="account.php">' . th('nav_account') . '</a>';
        }
        echo '<form method="post" action="logout.php" class="inline">' . csrf_field()
           . '<button>' . th('nav_logout') . '</button></form>';
    } else {
        if ($currentPage !== 'login.php') {
            echo '<a href="login.php">' . th('nav_login') . '</a>';
        }
        if ($currentPage !== 'register.php') {
            echo '<a href="register.php">' . th('nav_register') . '</a>';
        }
    }
    echo lang_switcher() . '</nav></div></header>'
       . '<main class="content"><div class="panel"><h1>' . h($title) . '</h1>';
}

/** Sprogvælger: det aktive sprog er fed, de andre er links til samme side. */
function lang_switcher(): string
{
    $currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $links = [];
    foreach (LANGS as $code => $name) {
        if ($code === lang()) {
            $links[] = '<strong>' . h($name) . '</strong>';
        } else {
            $queryParams = array_merge($_GET, ['lang' => $code]);
            $links[] = '<a href="' . h($currentPage . '?' . http_build_query($queryParams)) . '" hreflang="' . h($code) . '" lang="' . h($code) . '">' . h($name) . '</a>';
        }
    }
    return '<span class="lang">' . implode(' | ', $links) . '</span>';
}

function page_foot(): void
{
    echo '</div></main><footer class="site-footer"><div class="site-footer__inner">'
       . '<span>&copy; ' . date('Y') . ' <a href="https://rpix.dk/">rpix.dk</a></span><nav>'
       . '<a href="index.php">' . th('site_name') . '</a>'
       . '<a href="privacy.php">' . th('privacy_title') . '</a>'
       . '<a href="faq.php">' . th('faq_title') . '</a>'
       . '<a href="terms.php">' . th('terms_title') . '</a></nav></div></footer>';
    echo '</body></html>';
}

/** Skriver en besked. $kind er CSS-klassen: 'err' (fejl) eller 'ok'. */
function msg(string $text, string $kind = 'err'): void
{
    echo '<p class="' . h($kind) . '">' . h($text) . '</p>';
}
