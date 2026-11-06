<?php
declare(strict_types=1);

/*
 * Privatlivspolitik og brugsvilkår (da/en).
 * Teksterne er opdelt i afsnit: ['heading' => overskrift, 'items' => [...]].
 * Et punkt er enten en streng (afsnit), ['list' => [...]] (punktliste), ['paragraph' => tekst] (afsnit), eller har 'if' => 'inactive',
 * så det kun vises, når automatisk sletning af inaktive konti er slået til (config: inactive_days > 0).
 * Pladsholdere som {email} erstattes efter HTML-escaping, se legal_fill().
 */

/** Værdi fra config med standardværdi, så ældre config.php-filer ikke giver advarsler. */
function legal_cfg(string $key)
{
    global $CONFIG;
    $defaults = [
        'controller_name'  => '',
        'contact_email'    => '',
        'hosting_provider' => '',
        'web_log_days'     => 31,
        'server_log_days'  => 90,
        'inactive_days'    => 0,
        'legal_updated'    => '2026-11-01',
    ];
    return $CONFIG[$key] ?? $defaults[$key];
}

/** Værdi fra config, eller null hvis den ikke er sat. */
function cfg_opt(string $key)
{
    global $CONFIG;
    return $CONFIG[$key] ?? null;
}

/** Formatér en dato som '1. november 2026' (da) eller '1 November 2026' (en). */
function legal_date(string $isoDate): string
{
    $timestamp = strtotime($isoDate);
    if ($timestamp === false) {
        return $isoDate;
    }
    $month = (int)date('n', $timestamp);
    if (lang() === 'da') {
        $monthNames = ['januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august',
                  'september', 'oktober', 'november', 'december'];
        return date('j', $timestamp) . '. ' . $monthNames[$month - 1] . ' ' . date('Y', $timestamp);
    }
    return date('j F Y', $timestamp);
}

/** Indsætter værdier i en allerede HTML-escapet tekst. */
function legal_fill(string $html): string
{
    $contactEmailHtml = h((string)legal_cfg('contact_email'));
    $datatilsynetUrl = lang() === 'da' ? 'https://www.datatilsynet.dk' : 'https://www.datatilsynet.dk/english';
    return strtr($html, [
        '{name}'         => h((string)legal_cfg('controller_name')),
        '{email}'        => '<a href="mailto:' . $contactEmailHtml . '">' . $contactEmailHtml . '</a>',
        '{hoster}'       => h((string)legal_cfg('hosting_provider')),
        '{weblog}'       => (string)(int)legal_cfg('web_log_days'),
        '{serverlog}'    => (string)(int)legal_cfg('server_log_days'),
        '{inactive}'     => (string)(int)legal_cfg('inactive_days'),
        '{backup}'       => (string)(int)(cfg_opt('backup_days') ?? 14),
        '{keep}'         => (string)(int)(cfg_opt('inactive_keep_days') ?? 7),
        '{regttl}'       => (string)max(1, intdiv((int)cfg('register_ttl'), 3600)),
        '{updated}'      => h(legal_date((string)legal_cfg('legal_updated'))),
        '{datatilsynet}' => '<a href="' . $datatilsynetUrl . '">' . str_replace('https://', '', $datatilsynetUrl) . '</a>',
        '{account}'      => '<a href="account.php">' . th('nav_account') . '</a>',
        '{privacy}'      => '<a href="privacy.php">' . th('reg_accept_privacy') . '</a>',
        '{terms}'        => '<a href="terms.php">' . th('reg_accept_terms') . '</a>',
        '{minpw}'        => (string)(int)cfg('min_password'),
        '{source}'       => '<a href="https://github.com/lianergoist/caldav">github.com/lianergoist/caldav</a>',
        '{radicale}'     => '<a href="https://radicale.org">Radicale</a>',
        '{davnotes}'     => '<a href="davnotes.php">DavNotes</a>',
        '{maxusers}'     => number_format((int)cfg('max_users'), 0, ',', lang() === 'da' ? '.' : ','),
        '{invitedays}'   => (string)max(1, (int)cfg_or('waitlist_invite_days', 7)),
    ]);
}

/** Skriver privatlivspolitikken ('privacy') eller brugsvilkårene ('terms') på det aktuelle sprog. */
function legal_render(string $documentName): void
{
    $texts = legal_texts();
    $document = $texts[lang()][$documentName] ?? $texts['da'][$documentName];
    $showInactiveRules = (int)legal_cfg('inactive_days') > 0;
    echo '<p>' . legal_fill(h($document['intro'])) . '</p>';
    foreach ($document['sections'] as $section) {
        $sectionHtml = '';
        foreach ($section['items'] as $item) {
            if (is_array($item) && ($item['if'] ?? '') === 'inactive' && !$showInactiveRules) {
                continue;
            }
            if (is_string($item)) {
                $sectionHtml .= '<p>' . legal_fill(h($item)) . '</p>';
            } elseif (isset($item['list'])) {
                $sectionHtml .= '<ul>';
                foreach ($item['list'] as $listItem) {
                    if (is_array($listItem)) { // ['if' => 'inactive', 'text' => ...]
                        if (($listItem['if'] ?? '') === 'inactive' && !$showInactiveRules) {
                            continue;
                        }
                        $listItem = $listItem['text'];
                    }
                    $sectionHtml .= '<li>' . legal_fill(h($listItem)) . '</li>';
                }
                $sectionHtml .= '</ul>';
            } elseif (isset($item['paragraph'])) {
                $sectionHtml .= '<p>' . legal_fill(h($item['paragraph'])) . '</p>';
            }
        }
        if ($sectionHtml !== '') {
            echo '<h2>' . h($section['heading']) . '</h2>' . $sectionHtml;
        }
    }
}

function legal_texts(): array
{
    return [
        'da' => [
            'privacy' => [
                'intro' => 'Her kan du læse, hvilke oplysninger caldav.rpix.dk behandler om dig, hvorfor, og hvor længe de gemmes. Sidst opdateret {updated}.',
                'sections' => [
                    ['heading' => 'Dataansvarlig', 'items' => [
                        'Tjenesten drives af {name} som privatperson. Kontakt: {email}.',
                    ]],
                    ['heading' => 'Hvilke oplysninger', 'items' => [['list' => [
                        'Din e-mailadresse, som også er dit brugernavn.',
                        'Din adgangskode, gemt som en bcrypt-hash. Selve adgangskoden kan ikke læses af nogen, heller ikke af administratoren.',
                        'Det du selv lægger på serveren: kalendere, aftaler, opgaver, noter og kontakter. Kontakter kan indeholde oplysninger om andre end dig.',
                        'Tekniske logs: tidspunkt, IP-adresse, brugernavn, hvilken adresse på serveren der blev spurgt efter, og hvilket program der blev brugt (f.eks. DAVx⁵ eller Thunderbird).',
                        'Ved tilmelding og nulstilling: et engangslink og, ved tilmelding, din adgangskode som hash, indtil du har bekræftet kontoen.',
                        'Til beskyttelse mod misbrug: din IP-adresse og e-mailadresse sammen med tidspunktet for forsøg på tilmelding, login og nulstilling.',
                        'Hvis du står på ventelisten: din e-mailadresse, tidspunktet du kom på listen, dit sprogvalg, og om du har fået en invitation.',
                    ]]]],
                    ['heading' => 'Hvorfor, og med hvilken ret', 'items' => [['list' => [
                        'Konto, adgangskode og dit indhold behandles for at kunne levere den tjeneste, du har bedt om (GDPR artikel 6, stk. 1, litra b). Det samme gælder ventelisten, som du selv har bedt om at komme på.',
                        'Logs og oplysninger til beskyttelse mod misbrug behandles for at holde tjenesten sikker og stabil. Det er en legitim interesse (artikel 6, stk. 1, litra f).',
                        'Oplysningerne bruges ikke til markedsføring, profilering eller statistik om dig, og de sælges eller deles ikke med andre.',
                    ]]]],
                    ['heading' => 'Dit indhold', 'items' => [
                        'Kalendere kan indeholde følsomme oplysninger, f.eks. om helbred. Du bestemmer selv, hvad du lægger på serveren. Administratoren har teknisk adgang til serveren, men åbner ikke dit indhold, medmindre du selv beder om hjælp, eller det er nødvendigt for at rette en fejl eller opfylde et lovkrav.',
                        'Data krypteres, mens de sendes mellem dig og serveren (HTTPS), men de er ikke krypteret på serverens disk.',
                    ]],
                    ['heading' => 'Hvor data opbevares', 'items' => [
                        'Data ligger på en virtuel server hos {hoster}, som er databehandler. Data overføres ikke til lande uden for EU/EØS. Mails fra tjenesten (bekræftelse og nulstilling) sendes direkte fra serveren til din mailudbyder.',
                        'Hver nat tages en sikkerhedskopi af hele serveren til en server i Danmark, som administratoren selv har ansvaret for.',
                    ]],
                    ['heading' => 'Hvor længe', 'items' => [['list' => [
                        'Konto og indhold: indtil du sletter kontoen på siden {account}, eller tjenesten lukker.',
                        ['if' => 'inactive', 'text' => 'Konti, der ikke har været brugt i {inactive} dage, slettes automatisk med alt indhold. Du får en mail med varsel inden. Det tæller som brug, når et kalenderprogram synkroniserer. Data fra slettede inaktive konti gemmes i yderligere {keep} dage, så en fejl kan rettes. Derefter slettes de på serveren og forsvinder fra sikkerhedskopierne inden for {backup} dage.'],
                        'Webserverens logs: op til {weblog} dage.',
                        'Kalenderserverens logs: op til {serverlog} dage.',
                        'Engangslinks: til de er brugt eller udløbet, højst {regttl} timer. Oplysninger til beskyttelse mod misbrug: 24 timer.',
                        'Ventelisten: indtil du opretter en konto, melder dig af listen, eller en invitation til dig udløber efter {invitedays} dage uden at være brugt.',
                        'Sikkerhedskopier: op til {backup} dage. Derefter er de oplysninger, der er slettet på serveren, også væk fra sikkerhedskopierne.',
                        'Når du sletter din konto, slettes konto og indhold med det samme på serveren og forsvinder fra sikkerhedskopierne inden for {backup} dage. Det kan ikke fortrydes.',
                    ]]]],
                    ['heading' => 'Cookies', 'items' => [
                        'Portalen bruger kun to nødvendige cookies: en sessionscookie, der gør formularerne sikre og holder dig logget ind (den slettes, når du lukker browseren), og en cookie, der husker dit sprogvalg i et år. Der er ingen sporing, analyse eller reklamer.',
                    ]],
                    ['heading' => 'Dine rettigheder', 'items' => [
                        'Du har ret til at få indsigt i de oplysninger, der behandles om dig, og til at få dem rettet, slettet, begrænset eller udleveret i et maskinlæsbart format. Du kan også gøre indsigelse mod behandlingen.',
                        'Du kan selv slette din konto og alle data på siden {account}, og du kan eksportere dit indhold fra dit kalenderprogram eller fra Radicales webgrænseflade. Andre henvendelser kan sendes til {email}.',
                        'Du kan klage til Datatilsynet: {datatilsynet}.',
                    ]],
                    ['heading' => 'Sikkerhedsbrud', 'items' => [
                        'Sker der et brud på sikkerheden, som kan berøre dine oplysninger, anmeldes det til Datatilsynet, og du får besked, når reglerne kræver det.',
                    ]],
                    ['heading' => 'Ændringer', 'items' => [
                        'Ved væsentlige ændringer af denne politik får du besked på mail, før de træder i kraft.',
                    ]],
                ],
            ],
            'terms' => [
                'intro' => 'Når du opretter en konto, accepterer du disse vilkår. Sidst opdateret {updated}.',
                'sections' => [
                    ['heading' => 'Tjenesten', 'items' => [
                        'caldav.rpix.dk er en gratis server til kalendere, opgaver, noter og kontakter (CalDAV og CardDAV). Den drives af {name} som privatperson i fritiden, uden betaling og uden kommercielt formål.',
                    ]],
                    ['heading' => 'Kun til privat brug', 'items' => [
                        'Tjenesten er kun til personlig, privat brug. Den må ikke bruges af virksomheder, foreninger eller myndigheder, og den må ikke bruges til oplysninger om kunder, klienter, patienter eller medlemmer.',
                    ]],
                    ['heading' => 'Ingen garanti', 'items' => [['list' => [
                        'Tjenesten leveres, som den er, uden garanti for oppetid, hastighed eller fejlfrihed.',
                        'Der er ingen garanti for backup. Hav altid selv en kopi af dine data, f.eks. i dit kalenderprogram eller ved jævnligt at eksportere.',
                        'Brug ikke tjenesten til noget, hvor det kan få alvorlige følger, hvis data forsvinder eller er utilgængelige i en periode.',
                    ]]]],
                    ['heading' => 'Din konto', 'items' => [['list' => [
                        'Du skal bruge en e-mailadresse, du selv har adgang til.',
                        'Du har selv ansvaret for at vælge en sikker adgangskode og holde den hemmelig.',
                        'Kontoen er personlig og må ikke deles med andre eller overdrages.',
                    ]]]],
                    ['heading' => 'Rimelig brug', 'items' => [
                        'Du må ikke gemme ulovligt indhold, forsøge at få adgang til andres data, belaste serveren urimeligt (f.eks. med automatiske forespørgsler i stort omfang) eller bruge tjenesten til spam eller skadelig kode. Ved misbrug kan kontoen lukkes uden varsel.',
                    ]],
                    ['heading' => 'Inaktive konti', 'items' => [
                        ['if' => 'inactive', 'paragraph' => 'Konti, der ikke har været brugt i {inactive} dage, slettes automatisk med alt indhold. Du får en mail med varsel inden. Det tæller som brug, når et kalenderprogram synkroniserer.'],
                    ]],
                    ['heading' => 'Ændringer og lukning', 'items' => [
                        'Vilkårene kan ændres. Væsentlige ændringer varsles på mail mindst 30 dage før, de træder i kraft. Hvis tjenesten lukker, får du mindst 30 dages varsel på mail, så du kan nå at flytte dine data.',
                    ]],
                    ['heading' => 'Ansvar', 'items' => [
                        'Tjenesten er gratis, og i det omfang loven tillader det, er der intet ansvar for tab som følge af nedetid, datatab eller fejl. Det ændrer ikke på beskyttelsen af dine personoplysninger, som er beskrevet i {privacy}.',
                    ]],
                    ['heading' => 'Opsigelse', 'items' => [
                        'Du kan til enhver tid slette din konto og alle data på siden {account}.',
                    ]],
                    ['heading' => 'Lovvalg', 'items' => [
                        'Vilkårene følger dansk ret.',
                    ]],
                ],
            ],
            'faq' => [
                'intro' => 'Det er rimeligt at være skeptisk over for en gratis tjeneste, der skal passe på din kalender. Her er de ærlige svar på det, folk oftest spørger om. Mangler du et svar, så skriv til {email}.',
                'sections' => [
                    ['heading' => 'Hvem står bag?', 'items' => [
                        'Jeg hedder {name} og driver caldav.rpix.dk som privatperson i min fritid. Der er ikke noget firma, ingen investorer og ingen kunder bag. Du kan skrive direkte til mig på {email}.',
                    ]],
                    ['heading' => 'Hvorfor er det gratis? Hvad er ulempen?', 'items' => [
                        'Det er et fritidsprojekt uden kommercielt formål. Der er ingen reklamer, ingen sporing og ingen analyseværktøjer, og dine oplysninger bliver hverken solgt eller delt med nogen.',
                        'Ulempen er, at der ikke er nogen garanti. Tjenesten leveres, som den er, uden løfter om oppetid eller backup, og den er kun til privat brug. Se {terms}. Når det er sagt, så kan du godt forvente høj oppetid og god service.',
                    ]],
                    ['heading' => 'Hvor står serveren?', 'items' => [
                        'Hos {hoster}. Data sendes ikke ud af EU/EØS. Hver nat tages en sikkerhedskopi til en server i Danmark, som jeg selv har ansvaret for.',
                        'Mails fra tjenesten sendes direkte fra serveren til din mailudbyder, uden en ekstern mailtjeneste ind imellem.',
                    ]],
                    ['heading' => 'Kan du læse mine kalendere, kontakter og noter?', 'items' => [
                        'Ja, teknisk set. CalDAV og CardDAV har ingen end-to-end-kryptering. Data krypteres på vejen mellem dig og serveren (HTTPS), men de ligger ukrypteret på serverens disk. Sådan er det også hos de fleste andre kalenderudbydere.',
                        'Jeg åbner ikke dit indhold, medmindre du selv beder om hjælp, det er nødvendigt for at rette en fejl, eller loven kræver det. Men det er et løfte og ikke en teknisk garanti. Læg ikke noget på serveren, du ikke vil kunne leve med, at administratoren i princippet kan se.',
                    ]],
                    ['heading' => 'Hvad bliver logget?', 'items' => [
                        'Tidspunkt, IP-adresse, brugernavn, hvilken adresse på serveren der blev spurgt efter, og hvilket program der blev brugt. Indholdet af dine aftaler, kontakter og noter står ikke i loggene. Webserverens logs gemmes i højst {weblog} dage, og kalenderserverens i højst {serverlog} dage.',
                        'Portalen sætter kun to nødvendige cookies: én til at holde dig logget ind og én til at huske dit sprogvalg.',
                    ]],
                    ['heading' => 'Hvordan er min adgangskode beskyttet?', 'items' => [
                        'Den gemmes som en bcrypt-hash, så ingen kan læse den, heller ikke jeg. Den skal være på mindst {minpw} tegn, og portalen spærrer midlertidigt efter for mange forkerte login-forsøg.',
                        'Der er ingen tofaktorgodkendelse, fordi kalenderprogrammer logger ind med brugernavn og adgangskode. Brug derfor en adgangskode, du ikke bruger andre steder.',
                    ]],
                    ['heading' => 'Kan jeg komme ud igen?', 'items' => [
                        'Ja. Dine data ligger i åbne standardformater (iCalendar og vCard), så du kan flytte dem til en hvilken som helst anden CalDAV/CardDAV-server. Du kan eksportere .ics- og .vcf-filer fra Radicales webgrænseflade eller fra dit kalenderprogram.',
                        'Du kan slette din konto og alt indhold på siden {account}. Så slettes det med det samme på serveren, og det forsvinder fra sikkerhedskopierne inden for {backup} dage.',
                    ]],
                    ['heading' => 'Hvad sker der, hvis du lukker tjenesten?', 'items' => [
                        'Så får du mindst 30 dages varsel på mail, så du kan nå at flytte dine data.',
                        ['if' => 'inactive', 'paragraph' => 'Bemærk også, at konti, der ikke har været brugt i {inactive} dage, slettes automatisk. Du får en mail inden, og det tæller som brug, når et kalenderprogram synkroniserer.'],
                        'Der er plads til {maxusers} brugere, så serveren ikke bliver overbelastet. Er der fuldt, kan du skrive dig på ventelisten. Bliver en plads ledig, får den første på listen en mail og har {invitedays} dage til at oprette kontoen.',
                    ]],
                    ['heading' => 'Kan jeg se koden?', 'items' => [
                        'Selve kalenderserveren er {radicale}, en udbredt open source-server til CalDAV og CardDAV. Kontoportalen, som klarer tilmelding, nulstilling og sletning, har jeg skrevet selv, og koden ligger på {source}. Det samme gør koden til {davnotes} og WebNotes.',
                        'Du kan ikke selv tjekke, at det er præcis den kode, der kører på serveren. Men det hele er åben kildekode, så du kan læse, hvordan det virker, og køre det selv.',
                    ]],
                    ['heading' => 'Jeg vil hellere selv have kontrollen', 'items' => [
                        'Godt. Det er den bedste løsning, hvis du har mulighed for det. {radicale} er let at installere på din egen server eller en Raspberry Pi, og {davnotes} virker med alle CalDAV-servere, der understøtter noter (VJOURNAL), fx Radicale og Nextcloud. caldav.rpix.dk er tænkt til dem, der ikke har lyst til eller mulighed for at drive deres egen server.',
                    ]],
                    ['heading' => 'Hvad sker der, hvis serveren bliver hacket?', 'items' => [
                        'Sker der et brud på sikkerheden, som kan berøre dine oplysninger, anmelder jeg det til Datatilsynet, og du får besked, når reglerne kræver det. Læs mere i {privacy}.',
                    ]],
                ],
            ],
        ],

        'en' => [
            'privacy' => [
                'intro' => 'This page explains what information caldav.rpix.dk processes about you, why, and for how long it is kept. Last updated {updated}.',
                'sections' => [
                    ['heading' => 'Data controller', 'items' => [
                        'The service is run by {name} as a private individual. Contact: {email}.',
                    ]],
                    ['heading' => 'What information', 'items' => [['list' => [
                        'Your email address, which is also your username.',
                        'Your password, stored as a bcrypt hash. The password itself cannot be read by anyone, including the administrator.',
                        'What you put on the server yourself: calendars, events, tasks, notes and contacts. Contacts may contain information about people other than you.',
                        'Technical logs: time, IP address, username, the server address that was requested, and the program used (e.g. DAVx⁵ or Thunderbird).',
                        'During sign-up and password reset: a one-time link and, during sign-up, your password as a hash until you confirm the account.',
                        'To prevent abuse: your IP address and email address together with the time of sign-up, login and reset attempts.',
                        'If you are on the waiting list: your email address, the time you joined the list, your language, and whether you have been invited.',
                    ]]]],
                    ['heading' => 'Why, and on what legal basis', 'items' => [['list' => [
                        'Your account, password and content are processed to provide the service you asked for (GDPR Article 6(1)(b)). The same applies to the waiting list, which you asked to join.',
                        'Logs and abuse-prevention data are processed to keep the service secure and stable. This is a legitimate interest (Article 6(1)(f)).',
                        'The information is not used for marketing, profiling or statistics about you, and it is not sold or shared with anyone.',
                    ]]]],
                    ['heading' => 'Your content', 'items' => [
                        'Calendars can contain sensitive information, for example about health. You decide what you put on the server. The administrator has technical access to the server but does not open your content unless you ask for help, or it is necessary to fix a fault or comply with the law.',
                        'Data is encrypted while it travels between you and the server (HTTPS), but it is not encrypted on the server\'s disk.',
                    ]],
                    ['heading' => 'Where data is stored', 'items' => [
                        'Data is stored on a virtual server at {hoster}, which acts as data processor. Data is not transferred to countries outside the EU/EEA. Emails from the service (confirmation and reset) are sent directly from the server to your email provider.',
                        'Every night the whole server is backed up to a server in Denmark that the administrator is personally responsible for.',
                    ]],
                    ['heading' => 'How long', 'items' => [['list' => [
                        'Account and content: until you delete the account on the {account} page, or the service closes.',
                        ['if' => 'inactive', 'text' => 'Accounts that have not been used for {inactive} days are deleted automatically with all content. You will get an email warning first. A calendar program syncing counts as use. Data from deleted inactive accounts is kept for another {keep} days so that a mistake can be corrected. It is then deleted on the server and disappears from the backups within {backup} days.'],
                        'Web server logs: up to {weblog} days.',
                        'Calendar server logs: up to {serverlog} days.',
                        'One-time links: until used or expired, at most {regttl} hours. Abuse-prevention data: 24 hours.',
                        'Waiting list: until you create an account, leave the list, or an invitation to you expires unused after {invitedays} days.',
                        'Backups: up to {backup} days. After that, information deleted on the server is also gone from the backups.',
                        'When you delete your account, the account and content are deleted immediately on the server and disappear from the backups within {backup} days. This cannot be undone.',
                    ]]]],
                    ['heading' => 'Cookies', 'items' => [
                        'The portal only uses two necessary cookies: a session cookie that keeps the forms secure and keeps you logged in (deleted when you close the browser), and a cookie that remembers your language for one year. There is no tracking, analytics or advertising.',
                    ]],
                    ['heading' => 'Your rights', 'items' => [
                        'You have the right to access the information processed about you and to have it corrected, deleted, restricted or handed over in a machine-readable format. You can also object to the processing.',
                        'You can delete your account and all data yourself on the {account} page, and you can export your content from your calendar program or from the Radicale web interface. Other requests can be sent to {email}.',
                        'You can complain to the Danish Data Protection Agency (Datatilsynet): {datatilsynet}.',
                    ]],
                    ['heading' => 'Security breaches', 'items' => [
                        'If a security breach occurs that may affect your information, it will be reported to Datatilsynet, and you will be notified when the rules require it.',
                    ]],
                    ['heading' => 'Changes', 'items' => [
                        'If this policy changes significantly, you will be notified by email before the changes take effect.',
                    ]],
                ],
            ],
            'terms' => [
                'intro' => 'By creating an account, you accept these terms. Last updated {updated}.',
                'sections' => [
                    ['heading' => 'The service', 'items' => [
                        'caldav.rpix.dk is a free server for calendars, tasks, notes and contacts (CalDAV and CardDAV). It is run by {name} as a private individual in their spare time, free of charge and without commercial purpose.',
                    ]],
                    ['heading' => 'Private use only', 'items' => [
                        'The service is for personal, private use only. It may not be used by companies, associations or public authorities, and it may not be used for information about customers, clients, patients or members.',
                    ]],
                    ['heading' => 'No warranty', 'items' => [['list' => [
                        'The service is provided as is, with no guarantee of uptime, speed or freedom from errors.',
                        'There is no guaranteed backup. Always keep your own copy of your data, e.g. in your calendar program or by exporting regularly.',
                        'Do not use the service for anything where lost or temporarily unavailable data could have serious consequences.',
                    ]]]],
                    ['heading' => 'Your account', 'items' => [['list' => [
                        'You must use an email address you have access to.',
                        'You are responsible for choosing a secure password and keeping it secret.',
                        'The account is personal and may not be shared or transferred.',
                    ]]]],
                    ['heading' => 'Fair use', 'items' => [
                        'You may not store illegal content, try to access other people\'s data, put unreasonable load on the server (e.g. large-scale automated requests), or use the service for spam or malicious code. In case of abuse, the account may be closed without notice.',
                    ]],
                    ['heading' => 'Inactive accounts', 'items' => [
                        ['if' => 'inactive', 'paragraph' => 'Accounts that have not been used for {inactive} days are deleted automatically with all content. You will get an email warning first. A calendar program syncing counts as use.'],
                    ]],
                    ['heading' => 'Changes and closure', 'items' => [
                        'These terms may change. Significant changes will be announced by email at least 30 days before they take effect. If the service closes, you will get at least 30 days\' notice by email so you have time to move your data.',
                    ]],
                    ['heading' => 'Liability', 'items' => [
                        'The service is free, and to the extent permitted by law, there is no liability for losses caused by downtime, data loss or errors. This does not affect the protection of your personal data described in the {privacy}.',
                    ]],
                    ['heading' => 'Termination', 'items' => [
                        'You can delete your account and all data at any time on the {account} page.',
                    ]],
                    ['heading' => 'Governing law', 'items' => [
                        'These terms are governed by Danish law.',
                    ]],
                ],
            ],
            'faq' => [
                'intro' => 'It is reasonable to be sceptical of a free service that is supposed to look after your calendar. Here are honest answers to the questions people ask most often. If yours is missing, write to {email}.',
                'sections' => [
                    ['heading' => 'Who runs this?', 'items' => [
                        'My name is {name}, and I run caldav.rpix.dk as a private individual in my spare time. There is no company, no investors and no customers behind it. You can write to me directly at {email}.',
                    ]],
                    ['heading' => 'Why is it free? What is the catch?', 'items' => [
                        'It is a hobby project with no commercial purpose. There are no ads, no tracking and no analytics, and your information is never sold or shared with anyone.',
                        'The catch is that there is no guarantee. The service is provided as is, with no promise of uptime or backup, and it is for private use only. See the {terms}. But you can expect high uptime and good service, just not a guarantee.',
                    ]],
                    ['heading' => 'Where is the server?', 'items' => [
                        'At {hoster}. Data is not sent outside the EU/EEA. Every night a backup is made to a server in Denmark that I am personally responsible for.',
                        'Emails from the service are sent directly from the server to your email provider, with no third-party mail service in between.',
                    ]],
                    ['heading' => 'Can you read my calendars, contacts and notes?', 'items' => [
                        'Yes, technically. CalDAV and CardDAV have no end-to-end encryption. Data is encrypted on its way between you and the server (HTTPS), but it is stored unencrypted on the server\'s disk. The same is true for most other calendar providers.',
                        'I do not open your content unless you ask for help, it is necessary to fix a fault, or the law requires it. But that is a promise, not a technical guarantee. Do not put anything on the server that you could not live with the administrator being able to see in principle.',
                    ]],
                    ['heading' => 'What is logged?', 'items' => [
                        'Time, IP address, username, the server address that was requested, and the program used. The content of your events, contacts and notes is not in the logs. Web server logs are kept for at most {weblog} days, and calendar server logs for at most {serverlog} days.',
                        'The portal only sets two necessary cookies: one to keep you logged in and one to remember your language.',
                    ]],
                    ['heading' => 'How is my password protected?', 'items' => [
                        'It is stored as a bcrypt hash, so nobody can read it, including me. It must be at least {minpw} characters, and the portal temporarily blocks logins after too many failed attempts.',
                        'There is no two-factor authentication, because calendar programs log in with a username and password. So use a password you do not use anywhere else.',
                    ]],
                    ['heading' => 'Can I leave again?', 'items' => [
                        'Yes. Your data is stored in open standard formats (iCalendar and vCard), so you can move it to any other CalDAV/CardDAV server. You can export .ics and .vcf files from the Radicale web interface or from your calendar program.',
                        'You can delete your account and all content on the {account} page. It is then deleted immediately on the server and disappears from the backups within {backup} days.',
                    ]],
                    ['heading' => 'What happens if you shut the service down?', 'items' => [
                        'You will get at least 30 days\' notice by email, so you have time to move your data.',
                        ['if' => 'inactive', 'paragraph' => 'Also note that accounts that have not been used for {inactive} days are deleted automatically. You will get an email first, and a calendar program syncing counts as use.'],
                        'There is room for {maxusers} users, so the server does not get overloaded. If it is full, you can join the waiting list. When a place becomes free, the first person on the list gets an email and has {invitedays} days to create the account.',
                    ]],
                    ['heading' => 'Can I see the code?', 'items' => [
                        'The calendar server itself is {radicale}, a widely used open source server for CalDAV and CardDAV. I wrote the account portal, which handles sign-up, password reset and deletion, myself, and the code is at {source}. So is the code for {davnotes} and WebNotes.',
                        'You cannot check for yourself that exactly this code is running on the server. But it is all open source, so you can read how it works and run it yourself.',
                    ]],
                    ['heading' => 'I would rather be in control myself', 'items' => [
                        'Good. That is the best solution if you can. {radicale} is easy to install on your own server or a Raspberry Pi, and {davnotes} works with any CalDAV server that supports notes (VJOURNAL), such as Radicale and Nextcloud. caldav.rpix.dk is meant for those who do not want to, or cannot, run their own server.',
                    ]],
                    ['heading' => 'What happens if the server is hacked?', 'items' => [
                        'If a security breach occurs that may affect your information, I will report it to Datatilsynet (the Danish Data Protection Agency), and you will be notified when the rules require it. Read more in the {privacy}.',
                    ]],
                ],
            ],
        ],
    ];
}
