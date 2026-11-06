<?php
// Kopiér til config.php og tilpas. Filen skal ligge UDEN FOR webroden.
return [
    'base_url'               => 'https://caldav.rpix.dk',      // denne portal
    'radicale_url'           => 'https://caldav.rpix.dk/dav',    // vist til brugerne
    'radicale_internal_url'  => 'https://radicale.rpix.dk',    // bruges til at slette brugerdata (kan være http://127.0.0.1:5232)
    'htpasswd_file'          => '/etc/radicale/users/users',   // samme fil som i Radicales [auth] htpasswd_filename
    'db_file'                => __DIR__ . '/data/portal.sqlite',
    'from_email'             => 'noreply@rpix.dk',
    'from_name'              => 'rpix kalender',
    'max_users'              => 1000,       // loft over antal brugere i htpasswd (inkl. dig selv)
    'waitlist_invite_days'   => 7,        // så længe en invitation fra ventelisten holder pladsen
    'register_ttl'           => 86400,    // bekræftelseslink gyldigt 24 timer
    'reset_ttl'              => 3600,     // nulstillingslink gyldigt 1 time
    'min_password'           => 10,
    // Bruges i privatlivspolitik og brugsvilkår (privacy.php / terms.php)
    'controller_name'        => 'Dit Navn',
    'contact_email'          => 'dig@example.dk',
    'hosting_provider'       => 'Din Server Group ApS (Danmark)',
    'web_log_days'           => 31,       // Apache logrotate: rotate 31
    'server_log_days'        => 90,       // journald (Radicale) – skal sættes til at passe, se README
    'inactive_days'          => 0,       // 0 = slået fra. Over 0: reglen vises i tekster, og bin/inactive.php sletter
    'backup_days'            => 14,       // /root/vps-backup.sh: KEEP_SNAPSHOTS=14
    'legal_updated'          => '2026-11-06',
    // Sletning af inaktive konti (bin/inactive.php, køres fra /etc/cron.d/caldav-inactive)
    'inactive_warn_days'     => 7,        // advarsel så mange dage før sletning
    'inactive_keep_days'     => 7,        // slettede data ligger i karantæne så længe (/var/lib/radicale/deleted)
    'inactive_dry_run'       => true,     // true = vis kun i loggen, hvad der ville ske (journalctl -t caldav-inactive)
    'inactive_exempt'        => ['admin'],
    'radicale_storage'       => '/var/lib/radicale/collections',
];
