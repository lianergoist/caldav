<?php
declare(strict_types=1);

/*
 * Fælles top og sidefod til informationssiderne (index.php, davnotes.php), der bruger home.css.
 * Kontosiderne (login, tilmelding osv.) bruger page_head()/page_foot() i lib.php og style.css.
 */

/** Tekster, der går igen på informationssiderne. */
function home_t(string $key): string
{
    static $textsByLanguage = [
        'da' => [
            'account'  => 'Min konto',
            'signup'   => 'Opret gratis konto',
            'notes_button' => 'Noter i browseren',
            'radicale_button' => 'Kalendere og adressebøger',
            'notes_login_hint' => 'Log ind med den e-mail og adgangskode, du oprettede kontoen med.',
            'contact'  => 'Kontakt',
            'google_play_badge_alt'     => 'Hent på Google Play',
            'fdroid_badge_alt'   => 'Hent på F-Droid',
            'trademark_notice'       => 'Google Play og Google Play-logoet er varemærker tilhørende Google LLC.',
        ],
        'en' => [
            'account'  => 'My account',
            'signup'   => 'Create a free account',
            'notes_button' => 'Notes in the browser',
            'radicale_button' => 'Calendars and address books',
            'notes_login_hint' => 'Log in with the email and password you signed up with.',
            'contact'  => 'Contact',
            'google_play_badge_alt'     => 'Get it on Google Play',
            'fdroid_badge_alt'   => 'Get it on F-Droid',
            'trademark_notice'       => 'Google Play and the Google Play logo are trademarks of Google LLC.',
        ],
    ];
    return $textsByLanguage[lang()][$key] ?? $textsByLanguage['da'][$key];
}

const HOME_PLAY   = 'https://play.google.com/store/apps/details?id=dk.rpix.noter';
const HOME_FDROID = 'https://f-droid.org/en/packages/dk.rpix.noter/';
const HOME_NOTES  = '/notes/';

/** Lille note under knapperne til WebNotes og Radicales webgrænseflade: hvilket login de bruger. */
function home_notes_hint(): string
{
    return '<p class="login-hint">' . h(home_t('notes_login_hint')) . '</p>';
}

/** Butiks-badges til DavNotes. $extraHtml er ekstra HTML i samme række (fx en knap). */
function home_badges(string $extraHtml = '', string $extraClass = ''): string
{
    $langHtml = h(lang());   // vælger badge-billedet på det rigtige sprog
    return '<div class="badges' . ($extraClass !== '' ? ' ' . h($extraClass) : '') . '">'
        . '<a href="' . h(HOME_PLAY) . '"><img src="badges/gplay-' . $langHtml . '.png" width="646" height="250" alt="' . h(home_t('google_play_badge_alt')) . '"></a>'
        . '<a href="' . h(HOME_FDROID) . '"><img src="badges/fdroid-' . $langHtml . '.png" width="646" height="250" alt="' . h(home_t('fdroid_badge_alt')) . '"></a>'
        . $extraHtml . '</div>';
}

/**
 * Sender headers og skriver alt til og med <main>.
 * $backLink = [href, tekst] til linket øverst til venstre. $heroHtml er HTML inde i masthead__hero.
 */
function home_head(string $title, string $metaDescription, array $backLink, string $heroHtml): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    // Informationssiderne afhænger ikke af login. "Min konto" sender til login, hvis man ikke er logget ind.
    $navHtml = '<a href="account.php">' . h(home_t('account')) . '</a>';
    ?>
<!doctype html>
<html lang="<?= h(lang()) ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="light dark">
	<meta name="description" content="<?= h($metaDescription) ?>">
	<title><?= h($title) ?></title>
	<link rel="stylesheet" href="home.css">
</head>
<body>

<header class="masthead">
	<div class="masthead__top">
		<a class="masthead__home" href="<?= h($backLink[0]) ?>"><?= h($backLink[1]) ?></a>
		<nav class="masthead__nav">
			<?= $navHtml ?>
			<?= lang_switcher() ?>
		</nav>
	</div>
	<div class="masthead__hero">
<?= $heroHtml ?>
	</div>
</header>

<main class="content">
<?php
}

function home_foot(): void
{
    ?>
</main>

<footer class="site-footer">
	<div class="site-footer__inner">
		<span>&copy; <?= date('Y') ?> <a href="https://rpix.dk/">rpix.dk</a></span>
		<nav>
			<a href="index.php">caldav.rpix.dk</a>
			<a href="davnotes.php">DavNotes</a>
			<a href="faq.php"><?= th('faq_title') ?></a>
			<a href="privacy.php"><?= th('privacy_title') ?></a>
			<a href="terms.php"><?= th('terms_title') ?></a>
			<a href="mailto:<?= h((string)cfg('contact_email')) ?>"><?= h(home_t('contact')) ?></a>
		</nav>
		<p class="tm"><?= h(home_t('trademark_notice')) ?></p>
	</div>
</footer>

</body>
</html>
<?php
}
