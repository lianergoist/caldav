<?php
declare(strict_types=1);

/*
 * Sprog: dansk er standard. Valget gemmes i cookien "lang" og skiftes med ?lang=da / ?lang=en.
 * Tilføj et nyt sprog ved at tilføje det i LANGS og lave en ny blok i strings().
 */

const LANGS = ['da' => 'Dansk', 'en' => 'English'];

/** Det aktuelle sprog ('da' eller 'en'). Vælges med ?lang=..., ellers fra cookien, ellers dansk. */
function lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $lang = 'da';
    $requestedLang = $_GET['lang'] ?? null;
    if (is_string($requestedLang) && isset(LANGS[$requestedLang])) {
        $lang = $requestedLang;
        if (!headers_sent()) {
            setcookie('lang', $requestedLang, [
                'expires'  => time() + 365 * 86400,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    } elseif (isset($_COOKIE['lang']) && is_string($_COOKIE['lang']) && isset(LANGS[$_COOKIE['lang']])) {
        $lang = $_COOKIE['lang'];
    }
    return $lang;
}

/** Oversæt nøgle; ekstra argumenter indsættes med sprintf (%s / %d). Falder tilbage til dansk. */
function t(string $key, string|int ...$args): string
{
    $strings = strings();
    $text = $strings[lang()][$key] ?? $strings['da'][$key] ?? $key;
    return $args ? vsprintf($text, $args) : $text;
}

/** Oversæt til et bestemt sprog (bruges fra cron, hvor der ikke er en browser at spørge). */
function t_for_lang(string $lang, string $key, string|int ...$args): string
{
    $strings = strings();
    $text = $strings[$lang][$key] ?? $strings['da'][$key] ?? $key;
    return $args ? vsprintf($text, $args) : $text;
}

/** Oversæt og HTML-escape. */
function th(string $key, string|int ...$args): string
{
    return htmlspecialchars(t($key, ...$args), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Alle oversættelser: sprog => [nøgle => tekst]. */
function strings(): array
{
    static $strings = null;
    if ($strings !== null) {
        return $strings;
    }
    $strings = [
        'da' => [
            'site_name'      => 'caldav.rpix.dk',
            'nav_register'   => 'Tilmeld',
            'nav_account'    => 'Min konto',
            'nav_login'      => 'Log ind',
            'nav_logout'     => 'Log ud',

            'msg_csrf'        => 'Siden er udløbet – prøv igen.',
            'msg_csrf_reload' => 'Siden er udløbet – genindlæs og prøv igen.',
            'msg_rate'        => 'For mange forsøg. Prøv igen om lidt.',
            'msg_rate_ip'     => 'For mange forsøg fra din adresse. Prøv igen senere.',

            'label_email'    => 'E-mail',
            'label_password' => 'Adgangskode',
            'label_password2'=> 'Gentag adgangskode',
            'label_new'      => 'Ny adgangskode',
            'label_new2'     => 'Gentag ny adgangskode',

            'pw_mismatch'    => 'Adgangskoderne er ikke ens.',
            'pw_short'       => 'Adgangskoden skal være mindst %d tegn.',
            'pw_long'        => 'Adgangskoden må højst være 72 bytes (bcrypt-grænse).',

            'link_forgot'    => 'Glemt adgangskode',
            'link_home'      => 'Til forsiden',
            'link_to_register' => 'Til tilmelding',

            // tilmelding
            'reg_title'      => 'Opret konto',
            'reg_intro'      => 'Opret en konto til kalendere og kontakter. Dit brugernavn bliver din e-mailadresse.',
            'reg_invalid_email' => 'Ugyldig e-mailadresse.',
            'reg_closed'     => 'Pladserne blev optaget, før du nåede at bekræfte. Du kan skrive dig på ventelisten.',
            'reg_sent'       => 'Hvis adressen kan bruges, har vi sendt en mail med et bekræftelseslink. Tjek også spam-mappen.',
            'reg_submit'     => 'Opret konto',
            'reg_accept_pre' => 'Jeg accepterer ',
            'reg_accept_terms' => 'brugsvilkårene',
            'reg_accept_mid' => ' og har læst ',
            'reg_accept_privacy' => 'privatlivspolitikken',
            'reg_accept_post' => '.',
            'reg_accept_needed' => 'Du skal acceptere brugsvilkårene for at oprette en konto.',
            'privacy_title'  => 'Privatlivspolitik',
            'terms_title'    => 'Brugsvilkår',
            'faq_title'      => 'Kan jeg stole på det?',

            // venteliste
            'wl_title'       => 'Venteliste',
            'wl_full_intro'  => 'Alle %d pladser er optaget lige nu. Skriv dig på ventelisten, så får du en mail, når der er en plads til dig.',
            'wl_submit'      => 'Skriv mig på ventelisten',
            'wl_privacy_pre' => 'Så længe du står på listen, gemmer vi kun din e-mailadresse, tidspunktet og dit sprogvalg. Læs mere i ',
            'wl_sent'        => 'Hvis adressen kan bruges, har vi sendt en mail med et link, du skal klikke på for at komme på ventelisten. Tjek også spam-mappen.',
            'wl_confirm_intro'  => 'Bekræft, at du vil skrives på ventelisten med adressen ',
            'wl_confirm_submit' => 'Skriv mig på ventelisten',
            'wl_added'       => 'Du står nu på ventelisten som nr. %d. Du får en mail, når der er en plads til dig.',
            'wl_already'     => 'Du står allerede på ventelisten som nr. %d.',
            'wl_invalid'     => 'Linket er ugyldigt eller udløbet.',
            'wl_leave_intro' => 'Vil du af ventelisten? Så slettes din adresse fra listen med det samme.',
            'wl_leave_submit'=> 'Meld mig af ventelisten',
            'wl_left'        => 'Du er nu taget af ventelisten, og din adresse er slettet fra den.',
            'inv_intro'      => 'Der er en plads til dig. Vælg en adgangskode for at oprette kontoen ',
            'inv_invalid'    => 'Invitationen er ugyldig eller udløbet. Er der fuldt, kan du skrive dig på ventelisten igen.',
            'mail_wl_confirm_subject' => 'Bekræft, at du vil på ventelisten',
            'mail_wl_confirm_body'    => "Hej,\n\nDu (eller en anden) har bedt om at komme på ventelisten til kalenderserveren med denne adresse.\n\nBekræft her (linket virker i %d timer):\n%s\n\nVar det ikke dig, kan du ignorere denne mail. Adressen kommer ikke på listen uden bekræftelse.\n",
            'mail_wl_added_subject'   => 'Du står på ventelisten',
            'mail_wl_added_body'      => "Hej,\n\nDu står nu på ventelisten til kalenderserveren som nr. %d. Du får en mail, når der er en plads til dig.\n\nVil du af listen igen, så klik her:\n%s\n",
            'mail_invite_subject'     => 'Der er en plads til dig på kalenderserveren',
            'mail_invite_body'        => "Hej,\n\nNu er der en ledig plads til dig. Opret din konto her (linket virker i %d dage, derefter går pladsen videre til den næste på listen):\n%s\n\nVil du ikke have pladsen, kan du bare ignorere denne mail, eller melde dig af ventelisten her:\n%s\n",
            'reg_already'    => 'Allerede bruger?',
            'mail_exists_subject' => 'Din kalenderkonto findes allerede',
            'mail_exists_body'    => "Hej,\n\nDer blev forsøgt oprettet en konto med din adresse, men den findes allerede.\nHar du glemt din adgangskode, kan du nulstille den her:\n%s\n\nVar det ikke dig, kan du ignorere denne mail.\n",
            'mail_confirm_subject' => 'Bekræft din kalenderkonto',
            'mail_confirm_body'    => "Hej,\n\nDu (eller en anden) har bedt om at oprette en konto på kalenderserveren med denne adresse.\n\nBekræft her (linket virker i %d timer):\n%s\n\nVar det ikke dig, kan du ignorere denne mail – der oprettes ingen konto uden bekræftelse.\n",

            // bekræft
            'ver_title'      => 'Bekræft konto',
            'ver_intro'      => 'Bekræft oprettelsen af kontoen ',
            'ver_submit'     => 'Bekræft og opret konto',
            'ver_ok'         => 'Din konto er oprettet. Du kan nu logge ind og bruge den i din kalenderklient.',
            'ver_exists'     => 'Kontoen findes allerede.',
            'ver_expired'    => 'Siden er udløbet – genindlæs linket fra mailen og prøv igen.',
            'ver_error'      => 'Kontoen kunne ikke oprettes på grund af en serverfejl. Prøv igen senere.',
            'ver_invalid'    => 'Linket er ugyldigt eller udløbet. Opret kontoen igen.',

            // login
            'login_title'    => 'Log ind',
            'login_bad'      => 'Forkert e-mail eller adgangskode.',

            // min konto
            'acc_title'      => 'Min konto',
            'acc_current_wrong' => 'Nuværende adgangskode er forkert.',
            'acc_changed'    => 'Adgangskoden er ændret. Husk at opdatere den i dine kalenderklienter.',
            'acc_change_fail'=> 'Adgangskoden kunne ikke ændres. Prøv igen senere.',
            'acc_confirm_needed' => 'Sæt kryds for at bekræfte sletningen.',
            'acc_del_data_fail'  => 'Dine kalenderdata kunne ikke slettes, så kontoen er ikke slettet. Prøv igen senere.',
            'acc_del_acct_fail'  => 'Dine data er slettet, men kontoen kunne ikke fjernes. Kontakt administrator.',
            'acc_deleted_title'  => 'Konto slettet',
            'acc_deleted'    => 'Din konto og alle dine kalendere og kontakter er slettet.',
            'acc_conn'       => 'Forbindelse',
            'acc_username'   => 'Brugernavn',
            'acc_server'     => 'Server',
            'acc_server_alt' => 'Finder dit program ikke kalenderne, så brug den fulde adresse: %s',
            'acc_collection' => 'Din samling',
            'acc_del_h'      => 'Slet konto og data',
            'acc_del_text'   => 'Sletter din konto og alle dine kalendere, opgaver og kontakter på serveren. Det kan ikke fortrydes.',
            'acc_del_check'  => 'Ja, slet min konto og alle mine data permanent',
            'acc_del_btn'    => 'Slet konto',

            // nulstil
            'rst_new_title'  => 'Ny adgangskode',
            'rst_invalid'    => 'Linket er ugyldigt eller udløbet.',
            'rst_new_link'   => 'Bed om et nyt link',
            'rst_save'       => 'Gem adgangskode',
            'rst_sent'       => 'Hvis adressen har en konto, har vi sendt en mail med et link til at vælge en ny adgangskode.',
            'rst_send'       => 'Send link',
            'mail_reset_subject' => 'Nulstil din adgangskode',
            'mail_reset_body'    => "Hej,\n\nDu (eller en anden) har bedt om at nulstille adgangskoden til din kalenderkonto.\n\nVælg en ny adgangskode her (linket virker i %d minutter):\n%s\n\nVar det ikke dig, kan du ignorere denne mail – din adgangskode er uændret.\n",
            // inaktive konti (bin/inactive.php)
            'inact_warn_subject' => 'Din kalenderkonto bliver snart slettet',
            'inact_warn_body'    => "Hej,\n\nDin konto %s på caldav.rpix.dk har ikke været brugt siden %s.\n\nHvis den ikke bliver brugt inden %s, slettes kontoen automatisk sammen med alle dine kalendere, opgaver, noter og kontakter.\n\nDu beholder kontoen ved at logge ind her:\n%s\neller ved at lade et kalenderprogram synkronisere med kontoen.\n\nVil du ikke længere bruge kontoen, behøver du ikke gøre noget.\n",
            'inact_deleted_subject' => 'Din kalenderkonto er slettet',
            'inact_deleted_body'    => "Hej,\n\nDin konto %s på caldav.rpix.dk er slettet, fordi den ikke har været brugt i %d dage.\n\nVil du bruge tjenesten igen, kan du oprette en ny konto her:\n%s\n",
        ],

        'en' => [
            'site_name'      => 'caldav.rpix.dk',
            'nav_register'   => 'Sign up',
            'nav_account'    => 'My account',
            'nav_login'      => 'Log in',
            'nav_logout'     => 'Log out',

            'msg_csrf'        => 'The page has expired – please try again.',
            'msg_csrf_reload' => 'The page has expired – please reload and try again.',
            'msg_rate'        => 'Too many attempts. Please try again shortly.',
            'msg_rate_ip'     => 'Too many attempts from your address. Please try again later.',

            'label_email'    => 'Email',
            'label_password' => 'Password',
            'label_password2'=> 'Repeat password',
            'label_new'      => 'New password',
            'label_new2'     => 'Repeat new password',

            'pw_mismatch'    => 'The passwords do not match.',
            'pw_short'       => 'The password must be at least %d characters.',
            'pw_long'        => 'The password may be at most 72 bytes (bcrypt limit).',

            'link_forgot'    => 'Forgot password',
            'link_home'      => 'Back to start',
            'link_to_register' => 'Back to sign-up',

            'reg_title'      => 'Create account',
            'reg_intro'      => 'Create an account for calendars and contacts. Your username will be your email address.',
            'reg_invalid_email' => 'Invalid email address.',
            'reg_closed'     => 'The places were taken before you confirmed. You can join the waiting list.',
            'reg_sent'       => 'If the address can be used, we have sent an email with a confirmation link. Also check your spam folder.',
            'reg_submit'     => 'Create account',
            'reg_accept_pre' => 'I accept the ',
            'reg_accept_terms' => 'terms of use',
            'reg_accept_mid' => ' and have read the ',
            'reg_accept_privacy' => 'privacy policy',
            'reg_accept_post' => '.',
            'reg_accept_needed' => 'You must accept the terms of use to create an account.',
            'privacy_title'  => 'Privacy policy',
            'terms_title'    => 'Terms of use',
            'faq_title'      => 'Can I trust it?',

            // waiting list
            'wl_title'       => 'Waiting list',
            'wl_full_intro'  => 'All %d places are taken at the moment. Join the waiting list, and you will get an email when there is a place for you.',
            'wl_submit'      => 'Put me on the waiting list',
            'wl_privacy_pre' => 'While you are on the list, we only store your email address, the time and your language. Read more in the ',
            'wl_sent'        => 'If the address can be used, we have sent an email with a link you must click to join the waiting list. Also check your spam folder.',
            'wl_confirm_intro'  => 'Confirm that you want to join the waiting list with the address ',
            'wl_confirm_submit' => 'Put me on the waiting list',
            'wl_added'       => 'You are now number %d on the waiting list. You will get an email when there is a place for you.',
            'wl_already'     => 'You are already number %d on the waiting list.',
            'wl_invalid'     => 'The link is invalid or has expired.',
            'wl_leave_intro' => 'Do you want to leave the waiting list? Your address will be deleted from the list right away.',
            'wl_leave_submit'=> 'Remove me from the waiting list',
            'wl_left'        => 'You have been removed from the waiting list, and your address has been deleted from it.',
            'inv_intro'      => 'There is a place for you. Choose a password to create the account ',
            'inv_invalid'    => 'The invitation is invalid or has expired. If the server is full, you can join the waiting list again.',
            'mail_wl_confirm_subject' => 'Confirm that you want to join the waiting list',
            'mail_wl_confirm_body'    => "Hi,\n\nYou (or someone else) asked to join the waiting list for the calendar server with this address.\n\nConfirm here (the link is valid for %d hours):\n%s\n\nIf this was not you, you can ignore this email. The address is not added to the list without confirmation.\n",
            'mail_wl_added_subject'   => 'You are on the waiting list',
            'mail_wl_added_body'      => "Hi,\n\nYou are now number %d on the waiting list for the calendar server. You will get an email when there is a place for you.\n\nIf you want to leave the list, click here:\n%s\n",
            'mail_invite_subject'     => 'There is a place for you on the calendar server',
            'mail_invite_body'        => "Hi,\n\nA place has become available for you. Create your account here (the link is valid for %d days, after which the place goes to the next person on the list):\n%s\n\nIf you do not want the place, just ignore this email, or leave the waiting list here:\n%s\n",
            'reg_already'    => 'Already a user?',
            'mail_exists_subject' => 'Your calendar account already exists',
            'mail_exists_body'    => "Hi,\n\nSomeone tried to create an account with your address, but it already exists.\nIf you have forgotten your password, you can reset it here:\n%s\n\nIf this was not you, you can ignore this email.\n",
            'mail_confirm_subject' => 'Confirm your calendar account',
            'mail_confirm_body'    => "Hi,\n\nYou (or someone else) asked to create an account on the calendar server with this address.\n\nConfirm here (the link is valid for %d hours):\n%s\n\nIf this was not you, you can ignore this email – no account is created without confirmation.\n",

            'ver_title'      => 'Confirm account',
            'ver_intro'      => 'Confirm creation of the account ',
            'ver_submit'     => 'Confirm and create account',
            'ver_ok'         => 'Your account has been created. You can now log in and use it in your calendar client.',
            'ver_exists'     => 'The account already exists.',
            'ver_expired'    => 'The page has expired – reload the link from the email and try again.',
            'ver_error'      => 'The account could not be created because of a server error. Please try again later.',
            'ver_invalid'    => 'The link is invalid or has expired. Please create the account again.',

            'login_title'    => 'Log in',
            'login_bad'      => 'Wrong email or password.',

            'acc_title'      => 'My account',
            'acc_current_wrong' => 'The current password is wrong.',
            'acc_changed'    => 'Password changed. Remember to update it in your calendar clients.',
            'acc_change_fail'=> 'The password could not be changed. Please try again later.',
            'acc_confirm_needed' => 'Tick the box to confirm the deletion.',
            'acc_del_data_fail'  => 'Your calendar data could not be deleted, so the account has not been deleted. Please try again later.',
            'acc_del_acct_fail'  => 'Your data has been deleted, but the account could not be removed. Please contact the administrator.',
            'acc_deleted_title'  => 'Account deleted',
            'acc_deleted'    => 'Your account and all your calendars and contacts have been deleted.',
            'acc_conn'       => 'Connection',
            'acc_username'   => 'Username',
            'acc_server'     => 'Server',
            'acc_server_alt' => 'If your program cannot find your calendars, use the full address: %s',
            'acc_collection' => 'Your collection',
            'acc_del_h'      => 'Delete account and data',
            'acc_del_text'   => 'Deletes your account and all your calendars, tasks and contacts on the server. This cannot be undone.',
            'acc_del_check'  => 'Yes, permanently delete my account and all my data',
            'acc_del_btn'    => 'Delete account',

            'rst_new_title'  => 'New password',
            'rst_invalid'    => 'The link is invalid or has expired.',
            'rst_new_link'   => 'Request a new link',
            'rst_save'       => 'Save password',
            'rst_sent'       => 'If the address has an account, we have sent an email with a link to choose a new password.',
            'rst_send'       => 'Send link',
            'mail_reset_subject' => 'Reset your password',
            'mail_reset_body'    => "Hi,\n\nYou (or someone else) asked to reset the password for your calendar account.\n\nChoose a new password here (the link is valid for %d minutes):\n%s\n\nIf this was not you, you can ignore this email – your password is unchanged.\n",
            'inact_warn_subject' => 'Your calendar account will be deleted soon',
            'inact_warn_body'    => "Hi,\n\nYour account %s on caldav.rpix.dk has not been used since %s.\n\nIf it is not used before %s, the account will be deleted automatically together with all your calendars, tasks, notes and contacts.\n\nYou keep the account by logging in here:\n%s\nor by letting a calendar program sync with the account.\n\nIf you no longer want the account, you do not need to do anything.\n",
            'inact_deleted_subject' => 'Your calendar account has been deleted',
            'inact_deleted_body'    => "Hi,\n\nYour account %s on caldav.rpix.dk has been deleted because it was not used for %d days.\n\nIf you want to use the service again, you can create a new account here:\n%s\n",
        ],
    ];
    return $strings;
}

lang(); // sæt evt. cookie, før der sendes output
