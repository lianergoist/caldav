# Kalenderportal til Radicale (caldav.rpix.dk)

Lille PHP-app der lader folk oprette en Radicale-konto med e-mailbekræftelse,
nulstille glemt adgangskode og slette konto + data. Brugerne skrives til Radicales htpasswd-fil (bcrypt).

Krav: PHP 8.1+, `php-sqlite3`, `php-curl`, og `mail()` der kan sende (postfix på samme server).

## Mappestruktur

```
/var/www/rpix.dk/caldav/
├── config.php        (kopi af config.sample.php, tilpasset)
├── lib.php
├── lang.php          (oversættelser da/en; dansk er standard, valget gemmes i en cookie)
├── home_layout.php   (fælles top/sidefod til informationssiderne index.php og davnotes.php)
├── data/             (SQLite + lock-fil, uden for webroden)
└── public/           (DocumentRoot: index.php og davnotes.php (information, home.css),
                       register.php, verify.php, login.php, logout.php, account.php,
                       reset.php, privacy.php, terms.php (konto, style.css))
```

## 1. Rettigheder til htpasswd

PHP (www-data) skal kunne skrive filen, Radicale skal kunne læse den. Appen skriver atomisk via en
temp-fil i samme mappe, så **mappen** skal være skrivbar for www-data. Brug en dedikeret mappe med
setgid, så nye filer arves til Radicales gruppe:

```bash
sudo mkdir -p /etc/radicale/users
sudo cp <din-nuværende-htpasswd> /etc/radicale/users/users
sudo chown -R www-data:radicale /etc/radicale/users      # ret "radicale" til Radicales gruppe
sudo chmod 2775 /etc/radicale/users
sudo chmod 640 /etc/radicale/users/users

sudo mkdir -p /var/www/rpix.dk/caldav/data
sudo chown www-data:www-data /var/www/rpix.dk/caldav/data
sudo chmod 750 /var/www/rpix.dk/caldav/data
```

Bemærk: appen omskriver filen ved hver ændring og fjerner derfor evt. kommentarlinjer.
Eksisterende brugere med andre hashformater end bcrypt (`$2y$`) virker stadig i Radicale, men kan ikke
logge ind i portalen. Lav dem om med `htpasswd -B`.

## 2. Radicale (`config`)

```ini
[auth]
type = htpasswd
htpasswd_filename = /etc/radicale/users/users
htpasswd_encryption = bcrypt
delay = 1

[rights]
type = owner_only
```

Radicale kræver `passlib` og `bcrypt` til bcrypt (`pip install passlib bcrypt` i Radicales miljø).
Genstart Radicale én gang efter ændringen. Herefter genindlæses htpasswd løbende.

## 3. Portalens config

```bash
cp config.sample.php config.php
```

Ret `from_email`, `max_users` m.m. `radicale_url` er den adresse, brugerne får vist som server
(`https://caldav.rpix.dk/dav`, se `/dav/` i vhosten nedenfor). `radicale_internal_url` kan sættes til
`http://127.0.0.1:5232`, hvis Radicale lytter lokalt (så sletning ikke går ud over nettet).

## 4. Apache-vhost

Sådan ser `/etc/apache2/sites-available/caldav.rpix.dk-le-ssl.conf` ud på serveren. Http-udgaven
(`caldav.rpix.dk.conf`) sender bare alt videre til https.

```apache
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName caldav.rpix.dk
    DocumentRoot /var/www/rpix.dk/caldav/public

    ErrorLog  ${APACHE_LOG_DIR}/caldav.rpix.dk.error.log
    CustomLog ${APACHE_LOG_DIR}/caldav.rpix.dk.access.log combined

    <Directory /var/www/rpix.dk/caldav/public>
        Require all granted
        Options -Indexes
    </Directory>

    Header always set Strict-Transport-Security "max-age=31536000"

    # WebNotes (webklient til VJOURNAL) på /notes/. Den taler med Radicale via /dav/ nedenfor.
    # Gammel adresse: /noter/ sender videre til /notes/
    RedirectMatch 301 ^/noter(/.*)?$ /notes$1
    Alias /notes /var/www/rpix.dk/notes
    <Directory /var/www/rpix.dk/notes>
        Require all granted
        Options -Indexes
    </Directory>
    <Location /notes>
        Header always set Content-Security-Policy "default-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
        Header always set X-Content-Type-Options "nosniff"
        Header always set Referrer-Policy "no-referrer"
    </Location>

    # Autodiscovery (RFC 6764): programmer, der kun får "caldav.rpix.dk", finder selv Radicale
    RedirectMatch 301 ^/\.well-known/(caldav|carddav)/?$ /dav/

    # Radicale under /dav/ (portalen ligger på /). X-Script-Name får Radicale til at sætte /dav
    # foran sine adresser, så https://caldav.rpix.dk/dav/ kan bruges i kalenderprogrammer.
    RedirectMatch 301 ^/dav$ /dav/
    ProxyPass /dav/ http://localhost:5232/ retry=0
    ProxyPassReverse /dav/ http://localhost:5232/
    <Location /dav/>
        RequestHeader set X-Script-Name /dav
        RequestHeader set X-Forwarded-Port "%{SERVER_PORT}s"
        RequestHeader set X-Forwarded-Proto expr=%{REQUEST_SCHEME}
    </Location>

    # fælles certbot-certifikat for rpix.dk (dækker også caldav.rpix.dk)
    Include /etc/letsencrypt/options-ssl-apache.conf
    SSLCertificateFile /etc/letsencrypt/live/rpix.dk/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/rpix.dk/privkey.pem
</VirtualHost>
</IfModule>
```

`config.php`, `lib.php` og `data/` ligger uden for DocumentRoot og kan ikke hentes udefra.

Kræver `mod_alias`, `mod_proxy`, `mod_proxy_http` og `mod_headers`. Om WebNotes: se README i `/var/www/rpix.dk/notes`.

## 5. Mail

Bekræftelses- og nulstillingsmails sendes med `mail()` fra `noreply@rpix.dk`. Sørg for SPF og DKIM på
rpix.dk, ellers havner de nemt i spam hos Gmail/Outlook.

## 6. Test før du åbner for andre

1. Opret en testkonto, bekræft via mailen, log ind i en kalenderklient.
2. Opret en kalender i klienten, og slet så kontoen i portalen. Tjek at mappen under
   `collection-root/<bruger>` er væk.
   Sletningen sker med HTTP `DELETE` på `/<bruger>/` med brugerens eget login. Hvis Radicale afviser det
   hos dig, bliver kontoen ikke slettet, og brugeren får en fejl, så der ikke efterlades forældreløse data.
3. Prøv "glemt adgangskode".
4. Overvej fail2ban på Radicales logfil (mislykkede logins) ud over portalens indbyggede forsøgsgrænser.

## Sikkerhed i korthed

- Adgangskoder gemmes kun som bcrypt-hash (også i ventende tilmeldinger); tokens gemmes som SHA-256.
- Bekræftelse og nulstilling kræver en POST (mailscannere kan ikke "bruge" linket ved at hente det).
- CSRF-token på alle formularer, sessionscookie med Secure/HttpOnly/SameSite, CSP-header.
- Forsøgsgrænser pr. IP, pr. konto og pr. mailadresse; honeypot-felt mod simple bots.
- Svar afslører ikke, om en e-mailadresse allerede har en konto.
- Sletning af konto kræver nuværende adgangskode.

## Privatlivspolitik og brugsvilkår

`public/privacy.php` og `public/terms.php` viser teksterne fra `legal.php` (da/en). Navn, kontaktmail,
hostingfirma og opbevaringstider står i `config.php` (`controller_name`, `contact_email`,
`hosting_provider`, `web_log_days`, `server_log_days`, `inactive_days`, `legal_updated`).
Ved tilmelding skal brugeren sætte kryds ved vilkårene. Afsnittet om sletning af inaktive konti vises kun,
når `inactive_days` er over 0. Ret `legal_updated` og giv brugerne besked ved væsentlige ændringer.

`server_log_days` skal passe med journald, f.eks. `/etc/systemd/journald.conf.d/retention.conf`:
`[Journal]` / `MaxRetentionSec=3month`, og derefter `systemctl restart systemd-journald`.

## Sletning af inaktive konti

`bin/inactive.php` køres hver nat kl. 4.17 som root fra `/etc/cron.d/caldav-inactive`. Loggen ses med
`journalctl -t caldav-inactive`.

- Aktivitet = et vellykket login i Radicale (alle synkroniseringer) eller et login i portalen.
- Advarsel på mail `inactive_warn_days` før sletning. Der slettes tidligst `inactive_warn_days` efter advarslen.
- Ved sletning fjernes brugeren fra htpasswd, og data flyttes til `/var/lib/radicale/deleted/`, hvor de
  ligger i `inactive_keep_days` dage. Fortryd: flyt mappen tilbage til `collection-root/<bruger>` og opret
  brugeren igen.
- `inactive_exempt` slettes aldrig. Konti, hvis navn ikke er en mailadresse, slettes heller ikke automatisk.
- Nødstop (mail til `contact_email`): hvis journalen ikke kan læses, hvis der ikke er set et login i 3
  døgn, eller hvis mere end 20 % (mindst 3) af konti ville blive slettet på én gang.
- `inactive_dry_run => true` skriver kun i loggen, hvad der ville ske. `--dry-run` og `--live` på
  kommandolinjen overstyrer det.

## Licens

MIT, se [LICENSE](LICENSE).
