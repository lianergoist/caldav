<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
require __DIR__ . '/../home_layout.php';
start_session();

/*
 * Forside: information om tjenesten. Selve tilmeldingen ligger i register.php.
 *
 * Alle sidens tekster står i $textsByLanguage herunder, én blok pr. sprog.
 * Navne, der ender på _html (nøgler) eller Html (variabler), er sikker HTML, der udskrives
 * direkte: enten tekst med HTML skrevet her i filen (ikke brugerinput), eller værdier, der
 * allerede er escapet med h(). Alt andet escapes med h(), når det udskrives.
 */

$davUrl          = rtrim((string)cfg('radicale_url'), '/') . '/';   // https://caldav.rpix.dk/dav/
$davUrlHtml      = h($davUrl);
$davHost         = (string)parse_url($davUrl, PHP_URL_HOST);         // caldav.rpix.dk
//$radicaleWebHtml = h($davUrl . '.web/');
$radicaleWebHtml = h($davUrl);
$inactiveDays    = (int)($GLOBALS['CONFIG']['inactive_days'] ?? 0); // 0 = konti slettes ikke automatisk

$textsByLanguage = [
'da' => [
    'page_title'   => 'Gratis CalDAV-server til kalender, opgaver, kontakter og noter',
    'meta_description'    => 'caldav.rpix.dk er en gratis CalDAV- og CardDAV-server til kalendere, opgaver, kontakter og noter. Virker med iPhone, Android, Thunderbird, Mac og mange andre.',
    'intro'    => 'En gratis server til din kalender, dine opgaver, kontakter og noter.',
    'key_facts'   => [['Gratis', 'kræver kun en e-mail'], ['CalDAV', 'kalendere og opgaver'], ['CardDAV', 'kontakter'], ['Noter', 'med DavNotes og WebNotes']],

    'uses_heading'   => 'Hvad kan du bruge det til?',
    'uses_intro'=> 'CalDAV og CardDAV er åbne standarder, som de fleste kalender- og kontaktprogrammer forstår. Dine data ligger ét sted, og alle dine enheder holder sig selv opdateret.',
    'uses'     => [
        ['Kalender', 'Kalendere', 'Lav lige så mange kalendere, du vil – fx én til arbejde, én til familien og én til klubben. Aftaler, du opretter på telefonen, dukker op på computeren og omvendt.'],
        ['Todo', 'Opgaver', 'Todo-lister med frister, prioritet og underopgaver. Sæt kryds på mobilen, og opgaven er også klaret på computeren.'],
        ['Adresser', 'Kontakter', 'Adressebøger med navne, telefonnumre, adresser og fødselsdage, så du ikke mister dem, når du skifter telefon.'],
        ['Noter', 'Noter', 'Noter gemmes på serveren ligesom aftaler og opgaver. Brug min app DavNotes på Android eller WebNotes i browseren.'],
    ],

    'apps_heading'   => 'Programmer og apps',
    'apps_intro'=> 'Her er nogle af de mest brugte. Næsten alt, der kan "tilføje en CalDAV- eller CardDAV-konto", virker.',
    'apps_by_platform' => [
        ['Android', [
            ['DAVx⁵', 'Synkroniserer kalendere, opgaver og kontakter ind i telefonen, så du kan bruge telefonens egen kalender- og kontakt-app, fx Google Kalender, Samsung Kalender eller Fossify. Gratis på F-Droid, koster lidt på Google Play.'],
            ['DavNotes', 'Min note-app. Gemmer noterne på serveren. <a href="davnotes.php">Læs mere</a>.', 'Noter'],
            ['jtx Board', 'Opgaver, noter og dagbog. Bruger DAVx⁵ til at synkronisere.', 'Todo · Noter'],
            ['Tasks.org', 'Populær todo-app med indbygget CalDAV.', 'Todo'],
        ]],
        ['iPhone, iPad og Mac', [
            ['Kalender, Påmindelser og Kontakter', 'Indbygget. På iPhone/iPad: Indstillinger → Apps → Kalender (eller Kontakter) → Konti → Tilføj konto → Andet → Tilføj CalDAV-konto (eller CardDAV-konto).'],
            ['Mac', 'Systemindstillinger → Internetkonti → Tilføj anden konto → CalDAV-konto eller CardDAV-konto. Så virker Kalender, Påmindelser og Kontakter.'],
        ]],
        ['Windows, Linux og Mac', [
            ['Thunderbird', 'Gratis. Kalendere, opgaver og adressebøger: Ny kalender → På netværket, og Ny adressebog → CardDAV.'],
            ['GNOME Kalender og Evolution', 'Linux. Tilføj serveren under Online-konti eller direkte i Evolution.'],
            ['KDE Kontact / Merkuro', 'Linux. Tilføj en "DAV groupware"-ressource.'],
            ['eM Client', 'Windows og Mac. Understøtter både CalDAV og CardDAV.'],
            ['Outlook', 'Kan ikke selv, men den gratis tilføjelse Outlook CalDav Synchronizer klarer det.'],
        ]],
        ['Til nørder', [
            ['vdirsyncer + khal, todoman og khard', 'Kalender, todo og kontakter i terminalen.'],
            ['Radicales webgrænseflade', 'På <a href="' . $radicaleWebHtml . '">' . $radicaleWebHtml . '</a> kan du oprette og slette kalendere, todo-lister, adressebøger og note-samlinger, og importere/eksportere .ics- og .vcf-filer.'],
        ]],
    ],
    'apps_web_warning' => 'Google Kalender og Outlook.com på nettet kan ikke forbinde til en CalDAV-server. Brug i stedet en app på telefonen eller et program på computeren.',

    'getting_started_heading' => 'Sådan kommer du i gang',
    'getting_started_steps'   => [
        ['Opret en konto', 'Det kræver kun en e-mailadresse. Du får en mail med et link, du skal klikke på for at bekræfte.'],
        ['Opret dine kalendere', 'Log ind på <a href="' . $radicaleWebHtml . '">Radicales webgrænseflade</a>, og opret … tadaa … en kalender. Eller en todo-liste, en adressebog eller en note-samling. Mange apps (fx DAVx⁵ og iPhone) kan også selv oprette kalendere.'],
        ['Tilføj kontoen i dit program', 'Indtast serveradressen, din e-mail som brugernavn og din adgangskode. Vælg så de kalendere og lister, du vil bruge.'],
    ],
    'server_label' => 'Server',
    'server_full_address_hint' => 'Finder dit program ikke kalenderne, så brug den fulde adresse: %s',
    'username_label'   => 'Brugernavn',
    'username_value' => 'den e-mailadresse, du oprettede kontoen med',
    'password_label'     => 'Adgangskode',
    'password_value'   => 'den, du valgte ved oprettelsen',

    'davnotes_tag'  => 'Noter',
    'davnotes_heading'     => 'DavNotes og WebNotes',
    'davnotes_teaser'=> 'Gem dine noter på serveren ligesom kalender og kontakter. Brug min gratis app DavNotes på Android, eller læs og skriv dine noter i browseren med WebNotes, når du har en konto her.',
    'davnotes_read_more'  => 'Læs mere',

    'data_heading'   => 'Din konto, dine data',
    'data_points'     => [
        'Gratis, uden reklamer og uden sporing.',
        'Serveren står i Danmark. Adgangskoder gemmes kun som krypterede hashes.',
        'Har du glemt din adgangskode, kan du få en ny. Du kan slette din konto og alle dine data, når du vil.',
        'Der er et begrænset antal pladser, og ingen garanti for oppetid eller backup. Hav altid selv en kopi af det vigtigste.',
    ],
    'data_inactive_deletion' => 'Konti, der ikke har været brugt i %d dage, slettes automatisk. Du får en mail inden. Det tæller som brug, når et program synkroniserer.',
    'data_read_more_html' => 'Læs svarene på <a href="faq.php">de oftest stillede spørgsmål om tillid</a>, og læs mere i <a href="privacy.php">privatlivspolitikken</a> og <a href="terms.php">brugsvilkårene</a>.',
    'behind_the_service_html'   => 'Bag tjenesten står <a href="https://radicale.org">Radicale</a>, en lille open source-server til CalDAV og CardDAV, og en lille kontoportal, jeg selv har skrevet.',

    'signup_box_heading'    => 'Flyt din kalender hjem',
    'signup_box_text'    => 'Gratis – og det tager et par minutter.',
],

'en' => [
    'page_title'   => 'Free CalDAV server for calendars, tasks, contacts and notes',
    'meta_description'    => 'caldav.rpix.dk is a free CalDAV and CardDAV server for calendars, tasks, contacts and notes. Works with iPhone, Android, Thunderbird, Mac and many more.',
    'intro'    => 'A free server for your calendar, tasks, contacts and notes.',
    'key_facts'   => [['Free', 'just needs an email'], ['CalDAV', 'calendars and tasks'], ['CardDAV', 'contacts'], ['Notes', 'with DavNotes and WebNotes']],

    'uses_heading'   => 'What can you use it for?',
    'uses_intro'=> 'CalDAV and CardDAV are open standards that most calendar and contact programs understand. Your data lives in one place, and all your devices keep themselves up to date.',
    'uses'     => [
        ['Calendar', 'Calendars', 'Create as many calendars as you like – one for work, one for the family, one for the club. Events you add on your phone show up on your computer and vice versa.'],
        ['Todo', 'Tasks', 'To-do lists with due dates, priorities and subtasks. Tick it off on your phone, and it is done on your computer too.'],
        ['Addresses', 'Contacts', 'Address books with names, phone numbers, addresses and birthdays, so you do not lose them when you change phones.'],
        ['Notes', 'Notes', 'Notes are stored on the server just like events and tasks. Use my app DavNotes on Android or WebNotes in the browser.'],
    ],

    'apps_heading'   => 'Programs and apps',
    'apps_intro'=> 'Here are some of the most popular. Almost anything that can "add a CalDAV or CardDAV account" works.',
    'apps_by_platform' => [
        ['Android', [
            ['DAVx⁵', 'Syncs calendars, tasks and contacts into the phone, so you can use the phone\'s own calendar and contacts apps, e.g. Google Calendar, Samsung Calendar or Fossify. Free on F-Droid, a small fee on Google Play.'],
            ['DavNotes', 'My notes app. Stores your notes on the server. <a href="davnotes.php">Read more</a>.', 'Notes'],
            ['jtx Board', 'Tasks, notes and journal. Uses DAVx⁵ to sync.', 'Todo · Notes'],
            ['Tasks.org', 'Popular to-do app with built-in CalDAV.', 'Todo'],
        ]],
        ['iPhone, iPad and Mac', [
            ['Calendar, Reminders and Contacts', 'Built in. On iPhone/iPad: Settings → Apps → Calendar (or Contacts) → Accounts → Add Account → Other → Add CalDAV Account (or CardDAV Account).'],
            ['Mac', 'System Settings → Internet Accounts → Add Other Account → CalDAV or CardDAV account. Calendar, Reminders and Contacts then just work.'],
        ]],
        ['Windows, Linux and Mac', [
            ['Thunderbird', 'Free. Calendars, tasks and address books: New Calendar → On the Network, and New Address Book → CardDAV.'],
            ['GNOME Calendar and Evolution', 'Linux. Add the server under Online Accounts or directly in Evolution.'],
            ['KDE Kontact / Merkuro', 'Linux. Add a "DAV groupware" resource.'],
            ['eM Client', 'Windows and Mac. Supports both CalDAV and CardDAV.'],
            ['Outlook', 'Cannot do it on its own, but the free add-in Outlook CalDav Synchronizer can.'],
        ]],
        ['For nerds', [
            ['vdirsyncer + khal, todoman and khard', 'Calendar, to-dos and contacts in the terminal.'],
            ['Radicale web interface', 'At <a href="' . $radicaleWebHtml . '">' . $radicaleWebHtml . '</a> you can create and delete calendars, to-do lists, address books and note collections, and import/export .ics and .vcf files.'],
        ]],
    ],
    'apps_web_warning' => 'Google Calendar and Outlook.com on the web cannot connect to a CalDAV server. Use an app on your phone or a program on your computer instead.',

    'getting_started_heading' => 'Getting started',
    'getting_started_steps'   => [
        ['Create an account', 'All you need is an email address. You will get an email with a link to click to confirm.'],
        ['Create your calendars', 'Log in to the <a href="' . $radicaleWebHtml . '">Radicale web interface</a> and create … ta-da … a calendar. Or a to-do list, an address book or a note collection. Many apps (e.g. DAVx⁵ and iPhone) can also create calendars themselves.'],
        ['Add the account in your program', 'Enter the server address, your email as username and your password. Then pick the calendars and lists you want to use.'],
    ],
    'server_label' => 'Server',
    'server_full_address_hint' => 'If your program cannot find your calendars, use the full address: %s',
    'username_label'   => 'Username',
    'username_value' => 'the email address you signed up with',
    'password_label'     => 'Password',
    'password_value'   => 'the one you chose when signing up',

    'davnotes_tag'  => 'Notes',
    'davnotes_heading'     => 'DavNotes and WebNotes',
    'davnotes_teaser'=> 'Store your notes on the server just like calendars and contacts. Use my free app DavNotes on Android, or read and write your notes in the browser with WebNotes once you have an account here.',
    'davnotes_read_more'  => 'More',

    'data_heading'   => 'Your account, your data',
    'data_points'     => [
        'Free, no ads and no tracking.',
        'The server is in Denmark. Passwords are only stored as encrypted hashes.',
        'If you forget your password, you can get a new one. You can delete your account and all your data whenever you like.',
        'There is a limited number of places, and no guarantee of uptime or backup. Always keep your own copy of what matters.',
    ],
    'data_inactive_deletion' => 'Accounts not used for %d days are deleted automatically. You get an email first. A program syncing counts as use.',
    'data_read_more_html' => 'See the <a href="faq.php">answers to common questions about trust</a>, and read more in the <a href="privacy.php">privacy policy</a> and the <a href="terms.php">terms of use</a>.',
    'behind_the_service_html'   => 'Behind the service is <a href="https://radicale.org">Radicale</a>, a small open source CalDAV and CardDAV server, and a small account portal I wrote myself.',

    'signup_box_heading'    => 'Bring your calendar home',
    'signup_box_text'    => 'Free – and it takes a couple of minutes.',
],
];
$text = $textsByLanguage[lang()] ?? $textsByLanguage['da'];

ob_start();
?>
		<h1 class="masthead__title">caldav<span>.rpix.dk</span></h1>
		<p class="masthead__lead"><?= h($text['intro']) ?></p>
		<div class="actions">
			<a class="btn" href="register.php"><?= h(home_t('signup')) ?> <span aria-hidden="true">&rarr;</span></a>
			<span class="actions__break" aria-hidden="true"></span>
			<a class="btn btn--ghost" href="<?= h(HOME_NOTES) ?>"><?= h(home_t('notes_button')) ?></a>
			<a class="btn btn--ghost" href="<?= $radicaleWebHtml ?>"><?= h(home_t('radicale_button')) ?></a>
		</div>
		<?= home_notes_hint() ?>
<?php
home_head($text['page_title'] . ' – caldav.rpix.dk', $text['meta_description'], ['https://rpix.dk/', '← rpix.dk'], (string)ob_get_clean());
?>

	<div class="facts">
		<?php foreach ($text['key_facts'] as [$factTitle, $factDetail]): ?>
			<div><b><?= h($factTitle) ?></b><span><?= h($factDetail) ?></span></div>
		<?php endforeach; ?>
	</div>

	<section class="section" id="brug">
		<h2 class="section__title"><?= h($text['uses_heading']) ?></h2>
		<p class="section__lead"><?= h($text['uses_intro']) ?></p>
		<div class="grid grid--4">
			<?php foreach ($text['uses'] as [$useTag, $useTitle, $useDescription]): ?>
				<article class="card">
					<span class="meta"><?= h($useTag) ?></span>
					<h3><?= h($useTitle) ?></h3>
					<p><?= h($useDescription) ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="section" id="start">
		<h2 class="section__title"><?= h($text['getting_started_heading']) ?></h2>
		<ol class="steps">
			<?php foreach ($text['getting_started_steps'] as [$stepTitle, $stepDescriptionHtml]): ?>
				<li><b><?= h($stepTitle) ?></b><span><?= $stepDescriptionHtml ?></span></li>
			<?php endforeach; ?>
		</ol>
		<dl class="conn">
			<dt><?= h($text['server_label']) ?></dt><dd><code><?= h($davHost) ?></code><br><span class="hint"><?= sprintf(h($text['server_full_address_hint']), '<code>' . $davUrlHtml . '</code>') ?></span></dd>
			<dt><?= h($text['username_label']) ?></dt><dd><?= h($text['username_value']) ?></dd>
			<dt><?= h($text['password_label']) ?></dt><dd><?= h($text['password_value']) ?></dd>
		</dl>
	</section>

	<section class="section" id="data">
		<h2 class="section__title"><?= h($text['data_heading']) ?></h2>
		<div class="box prose">
			<ul>
				<?php foreach ($text['data_points'] as $dataPoint): ?><li><?= h($dataPoint) ?></li><?php endforeach; ?>
				<?php if ($inactiveDays > 0): ?><li><?= h(sprintf($text['data_inactive_deletion'], $inactiveDays)) ?></li><?php endif; ?>
			</ul>
			<p><?= $text['data_read_more_html'] ?></p>
			<p><?= $text['behind_the_service_html'] ?></p>
		</div>
	</section>

	<section class="section" id="apps">
		<h2 class="section__title"><?= h($text['apps_heading']) ?></h2>
		<p class="section__lead"><?= h($text['apps_intro']) ?></p>
		<div class="grid grid--2">
			<?php foreach ($text['apps_by_platform'] as [$platform, $apps]): ?>
				<article class="card">
					<span class="meta"><?= h($platform) ?></span>
					<ul class="apps">
						<?php foreach ($apps as $app):
							[$appName, $appDescriptionHtml] = $app;
							$appTag = $app[2] ?? null;   // valgfri: fx "Todo · Noter"
						?>
							<li><b><?= h($appName) ?><?php if ($appTag !== null): ?><em><?= h($appTag) ?></em><?php endif; ?></b><span><?= $appDescriptionHtml ?></span></li>
						<?php endforeach; ?>
					</ul>
				</article>
			<?php endforeach; ?>
		</div>
		<p class="note"><?= h($text['apps_web_warning']) ?></p>
	</section>

	<section class="section" id="davnotes">
		<div class="feature feature--compact">
			<div>
				<span class="meta"><?= h($text['davnotes_tag']) ?></span>
				<h2><?= h($text['davnotes_heading']) ?></h2>
				<p class="lead"><?= h($text['davnotes_teaser']) ?></p>
				<?= home_badges('<a class="btn" href="davnotes.php">' . h($text['davnotes_read_more']) . ' <span aria-hidden="true">&rarr;</span></a>') ?>
			</div>
		</div>
	</section>

	<section class="section">
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
