<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\CookieConsentBundle\Enum\CategoryEnum;

/**
 * Localised preference-modal section groupings for the demo cookie inventory.
 */
final class DemoPreferenceSections
{
    /**
     * @return list<string>
     */
    public static function locales(): array
    {
        return DemoLocale::ALL;
    }

    /**
     * @return list<array{title: string, description: string, categories: list<string>}>
     */
    public static function forLocale(string $locale): array
    {
        $copy = self::COPY[$locale] ?? self::COPY[DemoLocale::DEFAULT];

        return [
            self::usageSection($locale),
            [
                'title'       => $copy[0]['title'],
                'description' => $copy[0]['description'],
                'categories'  => ['required'],
            ],
            [
                'title'       => $copy[1]['title'],
                'description' => $copy[1]['description'],
                'categories'  => ['functionality', CategoryEnum::CATEGORY_PREFERENCES],
            ],
            [
                'title'       => $copy[2]['title'],
                'description' => $copy[2]['description'],
                'categories'  => [CategoryEnum::CATEGORY_ANALYTICS, CategoryEnum::CATEGORY_MARKETING],
            ],
        ];
    }

    /**
     * @return array{title: string, description: string, categories: list<string>}
     */
    private static function usageSection(string $locale): array
    {
        foreach (GdprConsentCopy::defaults() as $row) {
            if ($row['locale'] !== $locale) {
                continue;
            }

            return [
                'title'       => $row['preferencesUsageTitle'],
                'description' => $row['preferencesUsageDescription'],
                'categories'  => [],
            ];
        }

        $fallback = GdprConsentCopy::defaults()[0];

        return [
            'title'       => $fallback['preferencesUsageTitle'],
            'description' => $fallback['preferencesUsageDescription'],
            'categories'  => [],
        ];
    }

    /**
     * @var array<string, list<array{title: string, description: string}>>
     */
    private const COPY = [
        'en' => [
            [
                'title'       => 'Strictly necessary',
                'description' => 'These cookies are essential for the website to function. They enable core features such as security, session management, consent storage and protection against cross-site request forgery. They cannot be disabled.',
            ],
            [
                'title'       => 'Functionality & experience',
                'description' => 'These optional cookies remember choices you make—such as interface layout or language—and help us deliver a more personalised browsing experience. They are only set with your consent under Article 6(1)(a) GDPR.',
            ],
            [
                'title'       => 'Analytics & marketing',
                'description' => 'These cookies help us understand how visitors use the site and allow us to measure the effectiveness of advertising campaigns. Third-party providers may combine this data with information from other services. Activated only with your consent.',
            ],
        ],
        'es' => [
            [
                'title'       => 'Estrictamente necesarias',
                'description' => 'Estas cookies son imprescindibles para el funcionamiento del sitio web. Permiten funciones básicas como la seguridad, la gestión de sesión, el almacenamiento del consentimiento y la protección frente a falsificación de peticiones. No pueden desactivarse.',
            ],
            [
                'title'       => 'Funcionalidad y experiencia',
                'description' => 'Estas cookies opcionales recuerdan decisiones que usted toma—como el diseño de la interfaz o el idioma—y nos ayudan a ofrecer una experiencia de navegación más personalizada. Solo se instalan con su consentimiento conforme al artículo 6.1.a) del RGPD.',
            ],
            [
                'title'       => 'Análisis y marketing',
                'description' => 'Estas cookies nos ayudan a comprender cómo los visitantes utilizan el sitio y nos permiten medir la eficacia de las campañas publicitarias. Los proveedores externos pueden combinar estos datos con información de otros servicios. Solo se activan con su consentimiento.',
            ],
        ],
        'it' => [
            [
                'title'       => 'Strettamente necessari',
                'description' => 'Questi cookie sono indispensabili al funzionamento del sito web. Abilitano funzionalità essenziali come sicurezza, gestione della sessione, memorizzazione del consenso e protezione contro le richieste cross-site. Non possono essere disattivati.',
            ],
            [
                'title'       => 'Funzionalità ed esperienza',
                'description' => 'Questi cookie facoltativi memorizzano le scelte che effettua—ad esempio layout dell’interfaccia o lingua—e ci aiutano a offrire un’esperienza di navigazione più personalizzata. Vengono impostati solo con il suo consenso ai sensi dell’art. 6(1)(a) GDPR.',
            ],
            [
                'title'       => 'Analisi e marketing',
                'description' => 'Questi cookie ci aiutano a comprendere come i visitatori utilizzano il sito e ci consentono di misurare l’efficacia delle campagne pubblicitarie. I fornitori terzi possono combinare tali dati con informazioni provenienti da altri servizi. Attivati solo con il suo consenso.',
            ],
        ],
        'fr' => [
            [
                'title'       => 'Strictement nécessaires',
                'description' => 'Ces cookies sont indispensables au fonctionnement du site web. Ils permettent des fonctionnalités essentielles telles que la sécurité, la gestion de session, le stockage du consentement et la protection contre la falsification de requêtes. Ils ne peuvent pas être désactivés.',
            ],
            [
                'title'       => 'Fonctionnalité et expérience',
                'description' => 'Ces cookies facultatifs mémorisent vos choix—comme la disposition de l’interface ou la langue—et nous aident à offrir une expérience de navigation plus personnalisée. Ils ne sont déposés qu’avec votre consentement au titre de l’art. 6(1)(a) du RGPD.',
            ],
            [
                'title'       => 'Analyse et marketing',
                'description' => 'Ces cookies nous aident à comprendre comment les visiteurs utilisent le site et nous permettent de mesurer l’efficacité des campagnes publicitaires. Les prestataires tiers peuvent combiner ces données avec des informations issues d’autres services. Activés uniquement avec votre consentement.',
            ],
        ],
        'de' => [
            [
                'title'       => 'Unbedingt erforderlich',
                'description' => 'Diese Cookies sind für das Funktionieren der Website unerlässlich. Sie ermöglichen grundlegende Funktionen wie Sicherheit, Sitzungsverwaltung, Speicherung der Einwilligung und Schutz vor Cross-Site-Request-Forgery. Sie können nicht deaktiviert werden.',
            ],
            [
                'title'       => 'Funktionalität und Nutzererlebnis',
                'description' => 'Diese optionalen Cookies speichern von Ihnen getroffene Entscheidungen—z. B. zur Oberflächengestaltung oder Sprache—und helfen uns, ein personalisierteres Surferlebnis zu bieten. Sie werden nur mit Ihrer Einwilligung gemäß Art. 6 Abs. 1 lit. a DSGVO gesetzt.',
            ],
            [
                'title'       => 'Analyse und Marketing',
                'description' => 'Diese Cookies helfen uns zu verstehen, wie Besucher die Website nutzen, und ermöglichen die Messung der Wirksamkeit von Werbekampagnen. Drittanbieter können diese Daten mit Informationen aus anderen Diensten kombinieren. Aktivierung nur mit Ihrer Einwilligung.',
            ],
        ],
        'pt' => [
            [
                'title'       => 'Estritamente necessários',
                'description' => 'Estes cookies são essenciais para o funcionamento do site. Permitem funcionalidades básicas como segurança, gestão de sessão, armazenamento do consentimento e proteção contra falsificação de pedidos. Não podem ser desativados.',
            ],
            [
                'title'       => 'Funcionalidade e experiência',
                'description' => 'Estes cookies opcionais memorizam escolhas que o utilizador faz—como o layout da interface ou o idioma—e ajudam-nos a oferecer uma experiência de navegação mais personalizada. Só são definidos com o seu consentimento nos termos do art. 6.º, n.º 1, al. a) do RGPD.',
            ],
            [
                'title'       => 'Análise e marketing',
                'description' => 'Estes cookies ajudam-nos a compreender como os visitantes utilizam o site e permitem medir a eficácia das campanhas publicitárias. Os fornecedores terceiros podem combinar estes dados com informações de outros serviços. Ativados apenas com o seu consentimento.',
            ],
        ],
        'nl' => [
            [
                'title'       => 'Strikt noodzakelijk',
                'description' => 'Deze cookies zijn essentieel voor het functioneren van de website. Ze maken kernfuncties mogelijk zoals beveiliging, sessiebeheer, opslag van toestemming en bescherming tegen cross-site request forgery. Ze kunnen niet worden uitgeschakeld.',
            ],
            [
                'title'       => 'Functionaliteit en ervaring',
                'description' => 'Deze optionele cookies onthouden keuzes die u maakt—zoals de interface-indeling of taal—en helpen ons een meer gepersonaliseerde browse-ervaring te bieden. Ze worden alleen geplaatst met uw toestemming op grond van art. 6 lid 1 onder a AVG.',
            ],
            [
                'title'       => 'Analyse en marketing',
                'description' => 'Deze cookies helpen ons te begrijpen hoe bezoekers de site gebruiken en stellen ons in staat de effectiviteit van advertentiecampagnes te meten. Externe aanbieders kunnen deze gegevens combineren met informatie uit andere diensten. Alleen geactiveerd met uw toestemming.',
            ],
        ],
        'pl' => [
            [
                'title'       => 'Ściśle niezbędne',
                'description' => 'Te pliki cookie są niezbędne do działania witryny. Umożliwiają podstawowe funkcje, takie jak bezpieczeństwo, zarządzanie sesją, przechowywanie zgody i ochrona przed fałszowaniem żądań między witrynami. Nie można ich wyłączyć.',
            ],
            [
                'title'       => 'Funkcjonalność i doświadczenie',
                'description' => 'Te opcjonalne pliki cookie zapamiętują dokonywane przez Ciebie wybory—np. układ interfejsu lub język—i pomagają nam zapewnić bardziej spersonalizowane przeglądanie. Są ustawiane wyłącznie za Twoją zgodą na podstawie art. 6 ust. 1 lit. a RODO.',
            ],
            [
                'title'       => 'Analityka i marketing',
                'description' => 'Te pliki cookie pomagają nam zrozumieć, w jaki sposób odwiedzający korzystają z witryny, i umożliwiają pomiar skuteczności kampanii reklamowych. Dostawcy zewnętrzni mogą łączyć te dane z informacjami z innych usług. Aktywowane wyłącznie za Twoją zgodą.',
            ],
        ],
        'ca' => [
            [
                'title'       => 'Estrictament necessàries',
                'description' => 'Aquestes galetes són imprescindibles per al funcionament del lloc web. Permeten funcions bàsiques com la seguretat, la gestió de sessió, l’emmagatzematge del consentiment i la protecció contra la falsificació de peticions. No es poden desactivar.',
            ],
            [
                'title'       => 'Funcionalitat i experiència',
                'description' => 'Aquestes galetes opcionals recorden decisions que preneu—com el disseny de la interfície o l’idioma—i ens ajuden a oferir una experiència de navegació més personalitzada. Només s’instal·len amb el vostre consentiment d’acord amb l’art. 6.1.a) del RGPD.',
            ],
            [
                'title'       => 'Anàlisi i màrqueting',
                'description' => 'Aquestes galetes ens ajuden a entendre com els visitants utilitzen el lloc i ens permeten mesurar l’eficàcia de les campanyes publicitàries. Els proveïdors externs poden combinar aquestes dades amb informació d’altres serveis. Només s’activen amb el vostre consentiment.',
            ],
        ],
    ];
}
