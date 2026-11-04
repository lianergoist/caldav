<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
require __DIR__ . '/../home_layout.php';
start_session();

/*
 * Informationsside om noter: DavNotes (Android) og WebNotes (webklienten på /notes/).
 *
 * Alle sidens tekster står i $textsByLanguage herunder, én blok pr. sprog.
 * Navne, der ender på _html (nøgler) eller Html (variabler), er sikker HTML, der udskrives
 * direkte: enten tekst med HTML skrevet her i filen (ikke brugerinput), eller værdier, der
 * allerede er escapet med h(). Alt andet escapes med h(), når det udskrives.
 */

$hostHtml          = h((string)parse_url((string)cfg('radicale_url'), PHP_URL_HOST));   // caldav.rpix.dk
$notesUrlHtml      = h(HOME_NOTES);
$googlePlayUrlHtml = h(HOME_PLAY);
$fdroidUrlHtml     = h(HOME_FDROID);

$textsByLanguage = [
'da' => [
    'page_title'   => 'DavNotes og WebNotes – noter på din egen server',
    'meta_description'    => 'DavNotes er en gratis Android-app, der gemmer dine noter på en CalDAV-server. Med en konto på caldav.rpix.dk kan du også bruge WebNotes i browseren.',
    'back_link'    => '← caldav.rpix.dk',
    'intro'    => 'De fleste af os har kalenderen liggende på en server, så den er den samme på mobil, tablet og computer. DavNotes og WebNotes gør det samme for dine noter.',

    'app_tag' => 'Android',
    'app_heading'    => 'DavNotes – noter, der følger med dig',
    'app_body_html' => '<p>DavNotes er en gratis app til Android, der gemmer dine noter på serveren, så de er de samme på alle dine enheder. Den virker også uden net og synkroniserer, når forbindelsen er tilbage. Du finder den på <a href="' . $googlePlayUrlHtml . '">Google Play</a> og <a href="' . $fdroidUrlHtml . '">F-Droid</a>.</p>'
                . '<p>Appen virker med alle CalDAV-servere, der kan gemme noter (VJOURNAL) – også din egen, hvis du har en.</p>',

    'webnotes_tag' => 'Browser',
    'webnotes_heading'    => 'WebNotes – dine noter i browseren',
    'webnotes_body_html' => '<p>Når du har en konto på ' . $hostHtml . ', kan du også læse og skrive dine noter på <a href="' . $notesUrlHtml . '">' . $hostHtml . '/notes</a>, på computeren eller på en telefon, hvor appen ikke er installeret.</p>'
                . '<p>Browseren spørger om brugernavn og adgangskode – brug samme e-mail og adgangskode som til serveren. Har du ikke en note-samling endnu, tilbyder WebNotes at oprette den første.</p>',
    'webnotes_image_alt'  => 'WebNotes i browseren på en mobil, hvor en note med en nummereret liste redigeres',
    'webnotes_image_caption'  => 'WebNotes i browseren.',

    'features_heading'   => 'Det kan de',
    'features'     => [
        'Gemmer automatisk, mens du skriver.',
        'Markdown: overskrifter, fed, kursiv, lister, afkrydsningslister, tabeller og links.',
        'Søg, brug tags, og hav flere note-samlinger.',
        'Importér og eksportér noter som .md eller .txt.',
        'Ændres en note to steder på én gang, gemmes den anden version som en kopi – du mister aldrig noget.',
        'Noterne er almindelige VJOURNAL-poster, så fx jtx Board kan læse de samme noter.',
    ],

    'getting_started_heading'  => 'Sådan kommer du i gang',
    'getting_started_steps'    => [
        ['Opret en konto', 'Det er gratis og kræver kun en e-mailadresse. <a href="register.php">Opret konto her</a>.'],
        ['Hent appen eller åbn WebNotes', 'Installer DavNotes fra Google Play eller F-Droid, eller gå til <a href="' . $notesUrlHtml . '">' . $hostHtml . '/notes</a>.'],
        ['Log ind', 'I appen skriver du serveren <code>' . $hostHtml . '</code>, din e-mail som brugernavn og din adgangskode. Opret så en note-samling, og skriv løs.'],
    ],

    'about_davnotes_link'    => 'Læs mere om DavNotes på rpix.dk',
    'signup_box_heading'    => 'Prøv det selv',
    'signup_box_text'    => 'Opret en gratis konto, og log ind i appen eller i browseren.',
],

'en' => [
    'page_title'   => 'DavNotes and WebNotes – notes on your own server',
    'meta_description'    => 'DavNotes is a free Android app that stores your notes on a CalDAV server. With an account on caldav.rpix.dk you can also use WebNotes in the browser.',
    'back_link'    => '← caldav.rpix.dk',
    'intro'    => 'Most of us keep our calendar on a server, so it is the same on phone, tablet and computer. DavNotes and WebNotes do the same for your notes.',

    'app_tag' => 'Android',
    'app_heading'    => 'DavNotes – notes that follow you',
    'app_body_html' => '<p>Not many notes apps can store notes on your own server. Luckily, I have made one that can.</p>'
                . '<p>DavNotes is a free Android app that stores your notes on the server, so they are the same on all your devices. It also works offline and syncs when the connection is back. Get it on <a href="' . $googlePlayUrlHtml . '">Google Play</a> or <a href="' . $fdroidUrlHtml . '">F-Droid</a>.</p>'
                . '<p>The app works with any CalDAV server that can store notes (VJOURNAL) – including your own, if you have one.</p>',

    'webnotes_tag' => 'Browser',
    'webnotes_heading'    => 'WebNotes – your notes in the browser',
    'webnotes_body_html' => '<p>With an account here, you can also read and write your notes at <a href="' . $notesUrlHtml . '">' . $hostHtml . '/notes</a>, on your computer or on a phone without the app.</p>'
                . '<p>The browser asks for a username and password – use the same email and password as for the server. If you do not have a note collection yet, WebNotes offers to create the first one.</p>',
    'webnotes_image_alt'  => 'WebNotes in the browser on a phone, editing a note with a numbered list',
    'webnotes_image_caption'  => 'WebNotes in the browser.',

    'features_heading'   => 'What they do',
    'features'     => [
        'Saves automatically while you type.',
        'Markdown: headings, bold, italics, lists, checklists, tables and links.',
        'Search, use tags and keep several note collections.',
        'Import and export notes as .md or .txt.',
        'If a note is changed in two places at once, the other version is kept as a copy – you never lose anything.',
        'Notes are plain VJOURNAL entries, so e.g. jtx Board can read the same notes.',
    ],

    'getting_started_heading'  => 'Getting started',
    'getting_started_steps'    => [
        ['Create an account', 'It is free and only needs an email address. <a href="register.php">Sign up here</a>.'],
        ['Get the app or open WebNotes', 'Install DavNotes from Google Play or F-Droid, or go to <a href="' . $notesUrlHtml . '">' . $hostHtml . '/notes</a>.'],
        ['Log in', 'In the app, enter the server <code>' . $hostHtml . '</code>, your email as username and your password. Then create a note collection and start writing.'],
    ],

    'about_davnotes_link'    => 'More about DavNotes on rpix.dk',
    'signup_box_heading'    => 'Try it yourself',
    'signup_box_text'    => 'Create a free account and log in in the app or the browser.',
],
];
$text = $textsByLanguage[lang()] ?? $textsByLanguage['da'];

ob_start();
?>
		<h1 class="masthead__title">Dav<span>Notes</span> og Web<span>Notes</span></h1>
		<p class="masthead__lead"><?= h($text['intro']) ?></p>
<?php
home_head($text['page_title'] . ' – caldav.rpix.dk', $text['meta_description'], ['index.php', $text['back_link']], (string)ob_get_clean());
?>

	<section class="section" id="app">
		<div class="feature feature--compact">
			<div class="prose">
				<span class="meta"><?= h($text['app_tag']) ?></span>
				<h2><?= h($text['app_heading']) ?></h2>
				<?= $text['app_body_html'] ?>
				<?= home_badges() ?>
			</div>
		</div>
	</section>

	<section class="section" id="notes">
		<div class="feature">
			<div class="prose">
				<span class="meta"><?= h($text['webnotes_tag']) ?></span>
				<h2><?= h($text['webnotes_heading']) ?></h2>
				<?= $text['webnotes_body_html'] ?>
				<div class="actions">
					<a class="btn" href="<?= $notesUrlHtml ?>"><?= h(home_t('notes_button')) ?> <span aria-hidden="true">&rarr;</span></a>
				</div>
			</div>
			<figure>
				<img src="davnotes-web.png" width="1080" height="1206" alt="<?= h($text['webnotes_image_alt']) ?>">
				<figcaption><?= h($text['webnotes_image_caption']) ?></figcaption>
			</figure>
		</div>
	</section>

	<section class="section" id="funktioner">
		<h2 class="section__title"><?= h($text['features_heading']) ?></h2>
		<div class="box prose">
			<ul>
				<?php foreach ($text['features'] as $feature): ?><li><?= h($feature) ?></li><?php endforeach; ?>
			</ul>
		</div>
	</section>

	<section class="section" id="start">
		<h2 class="section__title"><?= h($text['getting_started_heading']) ?></h2>
		<ol class="steps">
			<?php foreach ($text['getting_started_steps'] as [$stepTitle, $stepDescriptionHtml]): ?>
				<li><b><?= h($stepTitle) ?></b><span><?= $stepDescriptionHtml ?></span></li>
			<?php endforeach; ?>
		</ol>
<!--
		<p class="note"><a href="https://rpix.dk/projekter/davnotes/"><?= h($text['about_davnotes_link']) ?> &rarr;</a></p>
-->

		<div class="cta">
			<div>
				<strong><?= h($text['signup_box_heading']) ?></strong>
				<span><?= h($text['signup_box_text']) ?></span>
			</div>
			<a class="btn" href="register.php"><?= h(home_t('signup')) ?> <span aria-hidden="true">&rarr;</span></a>
		</div>
	</section>

<?php
home_foot();
