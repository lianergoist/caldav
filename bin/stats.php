<?php
declare(strict_types=1);

/*
 * Viser antal brugere, ventelisten og hvor mange der har været aktive for nylig.
 * Viser kun tal, ingen e-mailadresser. Ændrer ikke noget.
 *
 *   php bin/stats.php
 *
 * Aktivitet kommer fra activity-tabellen, som bin/inactive.php opdaterer hver nat ud fra
 * Radicales logins i journalen (og portalen, når nogen logger ind der).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../lib.php';

const SECONDS_PER_DAY = 86400;
const ACTIVE_PERIODS_IN_DAYS = [7, 30, 90];

$now = time();
$users = htpasswd_load();
$userCount = count($users);
$maxUsers = (int)cfg('max_users');

$countQuery = function (string $sql, array $params = []): int {
    $query = db()->prepare($sql);
    $query->execute($params);
    return (int)$query->fetchColumn();
};

$inviteCutoff = $now - waitlist_invite_ttl();
$waitingCount = $countQuery('SELECT COUNT(*) FROM waitlist WHERE invited IS NULL');
$openInviteCount = $countQuery('SELECT COUNT(*) FROM waitlist WHERE invited > ?', [$inviteCutoff]);
$expiredInviteCount = $countQuery('SELECT COUNT(*) FROM waitlist WHERE invited <= ?', [$inviteCutoff]);
$pendingSignupCount = $countQuery("SELECT COUNT(*) FROM tokens WHERE purpose = 'register'");
$freeSeats = max(0, $maxUsers - $userCount - waitlist_reserved());

// Seneste aktivitet pr. bruger, kun for brugere der stadig findes i htpasswd.
$lastSeenByUser = [];
foreach (db()->query('SELECT email, last_seen FROM activity')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($users[$row['email']])) {
        $lastSeenByUser[$row['email']] = (int)$row['last_seen'];
    }
}
$lastScan = db()->query("SELECT v FROM meta WHERE k = 'last_scan'")->fetchColumn();

$printRow = fn(string $label, string $value) => print($label . ":" . str_repeat(" ", max(1, 37 - mb_strlen($label . ":"))) . $value . "\n");

$printRow('Brugere', sprintf('%d af %d (%d%%)', $userCount, $maxUsers,
    $maxUsers > 0 ? (int)round(100 * $userCount / $maxUsers) : 0));
$printRow('Ledige pladser', (string)$freeSeats);
$printRow('Tilmeldinger, ikke bekræftet endnu', (string)$pendingSignupCount);
echo "\n";
$printRow('Venteliste, venter', (string)$waitingCount);
$printRow('Venteliste, inviteret (gyldig)', (string)$openInviteCount);
$printRow('Venteliste, inviteret (udløbet)', (string)$expiredInviteCount);
echo "\n";
foreach (ACTIVE_PERIODS_IN_DAYS as $days) {
    $since = $now - $days * SECONDS_PER_DAY;
    $activeCount = count(array_filter($lastSeenByUser, fn(int $lastSeen) => $lastSeen >= $since));
    $printRow("Aktive sidste $days dage", (string)$activeCount);
}
$printRow('Uden registreret aktivitet', (string)($userCount - count($lastSeenByUser)));
$printRow('Aktivitet opdateret (inactive.php)', $lastScan !== false ? date('Y-m-d H:i', (int)$lastScan) : 'aldrig');
