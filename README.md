# Calendar portal for Radicale (caldav.rpix.dk)

**English** | [Dansk](README.da.md)

Small PHP app that lets people create a Radicale account with email verification,
reset a forgotten password and delete their account + data. Users are written to Radicale's htpasswd file (bcrypt).

Requirements: PHP 8.1+, `php-sqlite3`, `php-curl`, and a working `mail()` (postfix on the same server).

## Directory structure

```
/var/www/rpix.dk/caldav/
├── config.php        (copy of config.sample.php, customised)
├── lib.php
├── lang.php          (translations da/en; Danish is the default, the choice is stored in a cookie)
├── home_layout.php   (shared header/footer for the information pages index.php and davnotes.php)
├── data/             (SQLite + lock file, outside the web root)
└── public/           (DocumentRoot: index.php and davnotes.php (information, home.css),
                       register.php, verify.php, login.php, logout.php, account.php,
                       reset.php, privacy.php, terms.php (account, style.css))
```

## 1. htpasswd permissions

PHP (www-data) must be able to write the file, and Radicale must be able to read it. The app writes atomically via a
temp file in the same directory, so the **directory** must be writable by www-data. Use a dedicated directory with
setgid so new files inherit Radicale's group:

```bash
sudo mkdir -p /etc/radicale/users
sudo cp <your-current-htpasswd> /etc/radicale/users/users
sudo chown -R www-data:radicale /etc/radicale/users      # change "radicale" to Radicale's group
sudo chmod 2775 /etc/radicale/users
sudo chmod 640 /etc/radicale/users/users

sudo mkdir -p /var/www/rpix.dk/caldav/data
sudo chown www-data:www-data /var/www/rpix.dk/caldav/data
sudo chmod 750 /var/www/rpix.dk/caldav/data
```

Note: the app rewrites the file on every change and therefore removes any comment lines.
Existing users with hash formats other than bcrypt (`$2y$`) still work in Radicale, but cannot
log in to the portal. Convert them with `htpasswd -B`.

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

Radicale needs `passlib` and `bcrypt` for bcrypt (`pip install passlib bcrypt` in Radicale's environment).
Restart Radicale once after the change. After that, htpasswd is reloaded continuously.

## 3. Portal config

```bash
cp config.sample.php config.php
```

Adjust `from_email`, `max_users` etc. `radicale_url` is the address shown to users as the server
(`https://caldav.rpix.dk/dav`, see `/dav/` in the vhost below). `radicale_internal_url` can be set to
`http://127.0.0.1:5232` if Radicale listens locally (so deletion does not go over the network).

## 4. Apache vhost

This is what `/etc/apache2/sites-available/caldav.rpix.dk-le-ssl.conf` looks like on the server. The http version
(`caldav.rpix.dk.conf`) just redirects everything to https.

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

    # WebNotes (web client for VJOURNAL) at /notes/. It talks to Radicale via /dav/ below.
    # Old address: /noter/ redirects to /notes/
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

    # Autodiscovery (RFC 6764): clients given only "caldav.rpix.dk" find Radicale by themselves
    RedirectMatch 301 ^/\.well-known/(caldav|carddav)/?$ /dav/

    # DAV clients (e.g. DavNotes) given just https://caldav.rpix.dk send PROPFIND to /.
    # Without this the portal answers 200 with HTML. Browsers (GET) still get the portal.
    RewriteEngine On
    RewriteCond %{REQUEST_METHOD} ^(PROPFIND|REPORT)$
    RewriteRule ^/?$ /dav/ [R=301,L]

    # Radicale under /dav/ (the portal is at /). X-Script-Name makes Radicale prefix its
    # addresses with /dav, so https://caldav.rpix.dk/dav/ can be used in calendar clients.
    RedirectMatch 301 ^/dav$ /dav/
    ProxyPass /dav/ http://localhost:5232/ retry=0
    ProxyPassReverse /dav/ http://localhost:5232/
    <Location /dav/>
        RequestHeader set X-Script-Name /dav
        RequestHeader set X-Forwarded-Port "%{SERVER_PORT}s"
        RequestHeader set X-Forwarded-Proto expr=%{REQUEST_SCHEME}
    </Location>

    # shared certbot certificate for rpix.dk (also covers caldav.rpix.dk)
    Include /etc/letsencrypt/options-ssl-apache.conf
    SSLCertificateFile /etc/letsencrypt/live/rpix.dk/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/rpix.dk/privkey.pem
</VirtualHost>
</IfModule>
```

`config.php`, `lib.php` and `data/` are outside the DocumentRoot and cannot be fetched from outside.

Requires `mod_alias`, `mod_rewrite`, `mod_proxy`, `mod_proxy_http` and `mod_headers`. For WebNotes, see [lianergoist/webnotes](https://github.com/lianergoist/webnotes).

## 5. Mail

Verification and reset emails are sent with `mail()` from `noreply@rpix.dk`. Make sure SPF and DKIM are set up for
rpix.dk, otherwise they easily end up in spam at Gmail/Outlook.

## 6. Test before opening to others

1. Create a test account, verify via the email, log in from a calendar client.
2. Create a calendar in the client, then delete the account in the portal. Check that the directory under
   `collection-root/<user>` is gone.
   Deletion is done with HTTP `DELETE` on `/<user>/` using the user's own login. If Radicale rejects this
   on your setup, the account is not deleted and the user gets an error, so no orphaned data is left behind.
3. Try "forgot password".
4. Consider fail2ban on Radicale's log file (failed logins) in addition to the portal's built-in rate limits.

## Security in brief

- Passwords are stored only as bcrypt hashes (also in pending sign-ups); tokens are stored as SHA-256.
- Verification and reset require a POST (mail scanners cannot "use" the link by fetching it).
- CSRF token on all forms, session cookie with Secure/HttpOnly/SameSite, CSP header.
- Rate limits per IP, per account and per email address; honeypot field against simple bots.
- Responses do not reveal whether an email address already has an account.
- Deleting an account requires the current password.

## Privacy policy and terms of use

`public/privacy.php` and `public/terms.php` show the texts from `legal.php` (da/en). Name, contact email,
hosting provider and retention periods are set in `config.php` (`controller_name`, `contact_email`,
`hosting_provider`, `web_log_days`, `server_log_days`, `inactive_days`, `legal_updated`).
At sign-up the user must tick the terms checkbox. The section about deleting inactive accounts is only shown
when `inactive_days` is above 0. Update `legal_updated` and notify users of significant changes.

`server_log_days` must match journald, e.g. `/etc/systemd/journald.conf.d/retention.conf`:
`[Journal]` / `MaxRetentionSec=3month`, followed by `systemctl restart systemd-journald`.

## Waiting list

There is room for `max_users` users (including admin). When it is full, `register.php` shows a waiting list
instead. People must confirm their address via a link in an email (`waitlist.php`) before they are added.

- When a place becomes free, the first person on the list gets an email with a personal link that holds the
  place for `waitlist_invite_days` days. The account is created directly with the link. If it is not used,
  the place goes to the next person.
- Places promised to the waiting list cannot be taken by ordinary sign-ups.
- Every email from the waiting list has a link to leave it.
- Invitations are sent when someone joins the list, when someone deletes their account, and every night from
  `bin/inactive.php`. To send invitations sooner, e.g. when an invitation expires or `max_users` is raised,
  run `bin/waitlist.php` hourly as www-data, e.g. in `/etc/cron.d/caldav-waitlist`:

  ```
  23 * * * * www-data /usr/bin/php /var/www/rpix.dk/caldav/bin/waitlist.php 2>&1 | /usr/bin/logger -t caldav-waitlist
  ```

## Deleting inactive accounts

`bin/inactive.php` runs every night at 4:17 as root from `/etc/cron.d/caldav-inactive`. The log can be viewed with
`journalctl -t caldav-inactive`.

- Activity = a successful login to Radicale (any sync) or a login to the portal.
- A warning is emailed `inactive_warn_days` before deletion. Deletion happens no earlier than `inactive_warn_days` after the warning.
- On deletion, the user is removed from htpasswd and the data is moved to `/var/lib/radicale/deleted/`, where it
  stays for `inactive_keep_days` days. To undo: move the directory back to `collection-root/<user>` and recreate
  the user.
- `inactive_exempt` accounts are never deleted. Accounts whose name is not an email address are not deleted automatically either.
- Emergency stop (email to `contact_email`): if the journal cannot be read, if no login has been seen for 3
  days, or if more than 20 % (at least 3) of the accounts would be deleted at once.
- `inactive_dry_run => true` only logs what would happen. `--dry-run` and `--live` on
  the command line override it.

## Radicale in a virtualenv, and upgrading

Ubuntu's Radicale package lags behind and lives in universe, where security fixes are not guaranteed.
So Radicale runs from a virtualenv with a pinned version, e.g. `/opt/radicale-3.9.0`, and `/opt/radicale`
is a symlink to the version in use. The systemd service (`/etc/systemd/system/radicale.service`) has
`ExecStart=/opt/radicale/bin/python -m radicale`.

```
python3 -m venv /opt/radicale-3.9.0
/opt/radicale-3.9.0/bin/pip install "radicale[bcrypt]==3.9.0"
ln -sfn radicale-3.9.0 /opt/radicale
```

`bin/radicale-check.php` checks every week whether there is a new version on PyPI, and runs
[pip-audit](https://pypi.org/project/pip-audit/) (installed in `/opt/pip-audit`) against Radicale's packages.
It sends an email to `contact_email` but does not upgrade by itself, because even small Radicale releases
can change config or behaviour. Cron, e.g. `/etc/cron.d/caldav-radicale-check`:

```
41 6 * * 1 www-data /usr/bin/php /var/www/rpix.dk/caldav/bin/radicale-check.php 2>&1 | /usr/bin/logger -t caldav-radicale-check
```

### Upgrading Radicale

1. Read the [CHANGELOG](https://github.com/Kozea/Radicale/blob/master/CHANGELOG.md) for every version since
   the installed one. Look especially for "Breaking", config changes, username rules, and changes to
   htpasswd/bcrypt and to the log line `Successful login: '<user>' (`, which `bin/inactive.php` reads.
2. Build the new version alongside: `python3 -m venv /opt/radicale-X.Y.Z` and
   `/opt/radicale-X.Y.Z/bin/pip install "radicale[bcrypt]==X.Y.Z"`.
3. Test on a copy of the data: copy `/var/lib/radicale/collections` to a temporary folder and run
   `runuser -u radicale -- /opt/radicale-X.Y.Z/bin/python -m radicale --config /etc/radicale/config --storage-filesystem-folder <copy> --verify-storage`.
   Optionally start a test server with `--server-hosts 127.0.0.1:5299` and a test htpasswd
   (`--auth-htpasswd-filename`), and try logging in, creating a collection and deleting a user.
   Delete the copy afterwards, as it contains users' data.
4. `systemctl stop radicale`, make a backup
   (`tar -czpf /root/radicale-backup-<date>.tar.gz /var/lib/radicale /etc/radicale /etc/systemd/system/radicale.service`),
   switch the symlink with `ln -sfn radicale-X.Y.Z /opt/radicale`, and `systemctl start radicale`.
5. Check `journalctl -u radicale` for errors and for `Successful login` from real users.
6. Undo: `ln -sfn radicale-<old version> /opt/radicale` and `systemctl restart radicale`. If data has been
   damaged, unpack the backup as well. The old folder can be deleted once the new one has run for a while.

## License

MIT, see [LICENSE](LICENSE).
