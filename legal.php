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
        '{privacy}'      => '<a href="privacy.php">' . th('privacy_title') . '</a>',
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
                    ]]]],
                    ['heading' => 'Hvorfor, og med hvilken ret', 'items' => [['list' => [
                        'Konto, adgangskode og dit indhold behandles for at kunne levere den tjeneste, du har bedt om (GDPR artikel 6, stk. 1, litra b).',
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
                    ]]]],
                    ['heading' => 'Why, and on what legal basis', 'items' => [['list' => [
                        'Your account, password and content are processed to provide the service you asked for (GDPR Article 6(1)(b)).',
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
        ],
    ];
}
