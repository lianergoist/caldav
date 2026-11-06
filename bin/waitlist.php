<?php
declare(strict_types=1);

/*
 * Inviterer de næste på ventelisten, når der er ledige pladser, og fjerner udløbne invitationer.
 * Køres hver time som www-data fra /etc/cron.d/caldav-waitlist. Portalen kalder det samme,
 * når nogen kommer på listen eller sletter deres konto, så cron er kun et sikkerhedsnet
 * (fx når max_users bliver sat op, eller en invitation udløber).
 *
 *   php bin/waitlist.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../lib.php';

$sent = waitlist_send_invites();
$waiting = (int)db()->query('SELECT COUNT(*) FROM waitlist WHERE invited IS NULL')->fetchColumn();
$invited = (int)db()->query('SELECT COUNT(*) FROM waitlist WHERE invited IS NOT NULL')->fetchColumn();
if ($sent > 0 || $waiting > 0 || $invited > 0) {
    printf("Brugere %d/%d. Venter %d, inviteret %d (heraf %d nye).\n",
        count(htpasswd_load()), (int)cfg('max_users'), $waiting, $invited, $sent);
}
