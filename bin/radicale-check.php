<?php
declare(strict_types=1);

/*
 * Tjekker, om der er en ny version af Radicale, og om Radicales Python-pakker har kendte
 * sårbarheder. Sender en mail til contact_email, men opgraderer ikke selv: se README
 * ("Opgradering af Radicale") for fremgangsmåden.
 *
 * Køres ugentligt som www-data fra /etc/cron.d/caldav-radicale-check:
 *   php bin/radicale-check.php           mail kun, når der er noget nyt
 *   php bin/radicale-check.php --verbose skriv status, også når alt er i orden
 *
 * Config (valgfrit): radicale_venv (standard /opt/radicale, et symlink til den aktive version), pip_audit (standard /opt/pip-audit/bin/pip-audit).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../lib.php';

$verbose = in_array('--verbose', $argv, true);
$venv = rtrim((string)cfg_or('radicale_venv', '/opt/radicale'), '/');
$pipAudit = (string)cfg_or('pip_audit', '/opt/pip-audit/bin/pip-audit');
$python = $venv . '/bin/python';

function run(string $command, ?int &$exitCode = null): string
{
    exec($command . ' 2>&1', $outputLines, $exitCode);
    return trim(implode("\n", $outputLines));
}

function state_get(string $key): ?string
{
    $query = db()->prepare('SELECT v FROM meta WHERE k = ?');
    $query->execute([$key]);
    $value = $query->fetchColumn();
    return $value === false ? null : (string)$value;
}

function state_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO meta (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')
        ->execute([$key, $value]);
}

$problems = [];
$news = [];

/* ---------- installeret og nyeste version ---------- */

$installed = run(escapeshellarg($python) . ' -c ' . escapeshellarg('import radicale; print(radicale.VERSION)'), $exitCode);
if ($exitCode !== 0 || !preg_match('/^\d+(\.\d+)+$/', $installed)) {
    $problems[] = "Kunne ikke læse den installerede version fra $python:\n$installed";
    $installed = null;
}

$curl = curl_init('https://pypi.org/pypi/radicale/json');
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'caldav-portal radicale-check']);
$response = curl_exec($curl);
$latest = is_string($response) ? (json_decode($response, true)['info']['version'] ?? null) : null;
if (!is_string($latest) || !preg_match('/^\d+(\.\d+)+$/', $latest)) {
    $problems[] = 'Kunne ikke hente den nyeste version fra PyPI (HTTP ' . curl_getinfo($curl, CURLINFO_RESPONSE_CODE) . ').';
    $latest = null;
}

if ($installed !== null && $latest !== null && version_compare($latest, $installed, '>')) {
    // Kun én mail pr. ny version.
    if (state_get('radicale_notified_version') !== $latest) {
        $news[] = "Radicale $latest er udkommet. Installeret: $installed.\n"
            . "Ændringer: https://github.com/Kozea/Radicale/blob/master/CHANGELOG.md\n"
            . "Læs dem, især linjer med 'Breaking', 'Change' eller config-ændringer, og følg\n"
            . "'Opgradering af Radicale' i README.da.md.";
        state_set('radicale_notified_version', $latest);
    }
}

/* ---------- kendte sårbarheder i Radicales pakker ---------- */

$audit = null;
if (is_executable($pipAudit)) {
    $sitePackages = run(escapeshellarg($python) . ' -c ' . escapeshellarg('import site; print(site.getsitepackages()[0])'));
    $cacheDir = sys_get_temp_dir() . '/pip-audit-cache-' . posix_getuid();
    $audit = run(escapeshellarg($pipAudit) . ' --progress-spinner off --cache-dir ' . escapeshellarg($cacheDir)
        . ' --path ' . escapeshellarg($sitePackages), $auditExit);
    if ($auditExit !== 0) {
        // Én mail pr. ny rapport, ikke hver uge med den samme.
        $auditHash = hash('sha256', $audit);
        if (state_get('pip_audit_hash') !== $auditHash) {
            $news[] = "pip-audit fandt problemer i Radicales pakker ($sitePackages):\n\n$audit\n\n"
                . "Opgradér Radicale (eller den berørte pakke) i $venv og genstart radicale.";
            state_set('pip_audit_hash', $auditHash);
        }
    } else {
        state_set('pip_audit_hash', '');
    }
} else {
    $problems[] = "pip-audit findes ikke på $pipAudit, så pakkerne er ikke tjekket for sårbarheder.";
}

/* ---------- rapport ---------- */

if ($verbose) {
    printf("Radicale installeret: %s, nyeste: %s\n", $installed ?? '?', $latest ?? '?');
    echo $audit !== null ? "pip-audit: $audit\n" : '';
}
$adminEmail = (string)cfg('contact_email');
$messages = array_merge($news, $problems);
if ($messages) {
    echo implode("\n\n", $messages), "\n";
    if ($adminEmail !== '') {
        $subject = $news ? 'Radicale: ' . ($latest !== null && $installed !== null && version_compare($latest, $installed, '>')
            ? "ny version $latest" : 'sikkerhedsadvarsel') : 'Radicale-tjek fejlede';
        send_mail($adminEmail, '[caldav] ' . $subject, implode("\n\n---\n\n", $messages) . "\n");
    }
}
