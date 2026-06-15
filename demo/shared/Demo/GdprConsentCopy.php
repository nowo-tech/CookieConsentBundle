<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * GDPR-aligned modal copy used by the demo seed command and data migrations.
 */
final class GdprConsentCopy
{
    /**
     * @return list<array{
     *     locale: string,
     *     title: string,
     *     intro: string,
     *     readMoreLabel: string,
     *     acceptAll: string,
     *     acceptNecessary: string,
     *     showPreferences: string,
     *     preferencesTitle: string,
     *     preferencesUsageTitle: string,
     *     preferencesUsageDescription: string,
     *     save: string,
     *     privacyRoute: string
     * }>
     */
    public static function defaults(): array
    {
        return [
            [
                'locale' => 'en',
                'title' => "Hello traveller, it's cookie time!",
                'intro' => 'Cookies on this website are used to personalise content and ads, provide social media features and analyse traffic. We also share information about your use of the website with our social media, advertising and analytics partners, who may combine it with other information you have provided to them or that they have collected from your use of their services.',
                'readMoreLabel' => 'Read our cookie policy',
                'acceptAll' => 'Accept all',
                'acceptNecessary' => 'Reject all',
                'showPreferences' => 'Manage preferences',
                'preferencesTitle' => 'Configure your preferences',
                'preferencesUsageTitle' => 'Cookie usage',
                'preferencesUsageDescription' => 'We use cookies and similar technologies. Strictly necessary cookies are always active because they are essential for the website to work (security, session, storing your consent). Analytics, marketing and preference cookies are optional and only activated with your consent under Article 6(1)(a) GDPR. You can accept all, reject non-essential cookies or customise categories below. You can withdraw consent at any time via “Cookie settings”.',
                'save' => 'Save preferences',
                'privacyRoute' => 'demo_privacy_policy',
            ],
            [
                'locale' => 'es',
                'title' => 'Hola viajero, ¡es la hora de las cookies!',
                'intro' => 'Las cookies de este sitio web se utilizan para personalizar el contenido y los anuncios, ofrecer funciones de redes sociales y analizar el tráfico. También compartimos información sobre el uso que haga del sitio web con nuestros partners de redes sociales, publicidad y análisis, que pueden combinarla con otra información que les haya proporcionado o que hayan recopilado del uso que haga de sus servicios.',
                'readMoreLabel' => 'Consultar la política de cookies',
                'acceptAll' => 'Aceptar todas',
                'acceptNecessary' => 'Rechazar todas',
                'showPreferences' => 'Gestionar preferencias',
                'preferencesTitle' => 'Configura tus preferencias',
                'preferencesUsageTitle' => 'Uso de cookies',
                'preferencesUsageDescription' => 'Utilizamos cookies y tecnologías similares. Las cookies estrictamente necesarias están siempre activas porque son imprescindibles para el funcionamiento del sitio (seguridad, sesión, almacenamiento del consentimiento). Las cookies de analítica, marketing y preferencias son opcionales y solo se activan con su consentimiento conforme al artículo 6.1.a) del RGPD. Puede aceptar todas, rechazar las no esenciales o personalizar las categorías a continuación. Puede retirar su consentimiento en cualquier momento mediante «Configuración de cookies».',
                'save' => 'Guardar preferencias',
                'privacyRoute' => 'demo_privacy_policy',
            ],
            [
                'locale' => 'it',
                'title' => 'Preferenze cookie',
                'intro' => 'Utilizziamo cookie e tecnologie simili. I cookie strettamente necessari sono sempre attivi perché indispensabili al funzionamento del sito (sicurezza, sessione, memorizzazione del consenso). I cookie di analisi, marketing e preferenze sono facoltativi e attivati solo con il tuo consenso ai sensi dell’art. 6(1)(a) GDPR. Puoi accettare tutti, rifiutare quelli non essenziali o personalizzare le categorie. Puoi revocare il consenso in qualsiasi momento tramite «Impostazioni cookie».',
                'readMoreLabel' => 'Leggi la cookie policy',
                'acceptAll' => 'Accetta tutti',
                'acceptNecessary' => 'Rifiuta tutti',
                'showPreferences' => 'Gestisci preferenze',
                'preferencesTitle' => 'Configura le tue preferenze',
                'preferencesUsageTitle' => 'Uso dei cookie',
                'preferencesUsageDescription' => 'Utilizziamo cookie e tecnologie simili. I cookie strettamente necessari sono sempre attivi perché indispensabili al funzionamento del sito (sicurezza, sessione, memorizzazione del consenso). I cookie di analisi, marketing e preferenze sono facoltativi e attivati solo con il tuo consenso ai sensi dell’art. 6(1)(a) GDPR. Puoi accettare tutti, rifiutare quelli non essenziali o personalizzare le categorie. Puoi revocare il consenso in qualsiasi momento tramite «Impostazioni cookie».',
                'save' => 'Salva preferenze',
                'privacyRoute' => 'demo_cookie_policy',
            ],
            [
                'locale' => 'fr',
                'title' => 'Préférences cookies',
                'intro' => 'Nous utilisons des cookies et technologies similaires. Les cookies strictement nécessaires sont toujours actifs car indispensables au fonctionnement du site (sécurité, session, stockage du consentement). Les cookies d’analyse, de marketing et de préférences sont facultatifs et activés uniquement avec votre consentement au titre de l’art. 6(1)(a) du RGPD. Vous pouvez tout accepter, refuser les cookies non essentiels ou personnaliser les catégories. Vous pouvez retirer votre consentement à tout moment via « Paramètres des cookies ».',
                'readMoreLabel' => 'Lire la politique cookies',
                'acceptAll' => 'Tout accepter',
                'acceptNecessary' => 'Tout refuser',
                'showPreferences' => 'Gérer les préférences',
                'preferencesTitle' => 'Configurer vos préférences',
                'preferencesUsageTitle' => 'Utilisation des cookies',
                'preferencesUsageDescription' => 'Nous utilisons des cookies et technologies similaires. Les cookies strictement nécessaires sont toujours actifs car indispensables au fonctionnement du site (sécurité, session, stockage du consentement). Les cookies d’analyse, de marketing et de préférences sont facultatifs et activés uniquement avec votre consentement au titre de l’art. 6(1)(a) du RGPD. Vous pouvez tout accepter, refuser les cookies non essentiels ou personnaliser les catégories. Vous pouvez retirer votre consentement à tout moment via « Paramètres des cookies ».',
                'save' => 'Enregistrer les préférences',
                'privacyRoute' => 'demo_cookie_policy',
            ],
            [
                'locale' => 'de',
                'title' => 'Cookie-Einstellungen',
                'intro' => 'Wir verwenden Cookies und ähnliche Technologien. Unbedingt erforderliche Cookies sind immer aktiv, da sie für den Betrieb der Website notwendig sind (Sicherheit, Sitzung, Speicherung der Einwilligung). Analyse-, Marketing- und Präferenz-Cookies sind optional und werden nur mit Ihrer Einwilligung gemäß Art. 6 Abs. 1 lit. a DSGVO aktiviert. Sie können alle akzeptieren, nicht erforderliche ablehnen oder Kategorien anpassen. Die Einwilligung können Sie jederzeit über „Cookie-Einstellungen“ widerrufen.',
                'readMoreLabel' => 'Cookie-Richtlinie lesen',
                'acceptAll' => 'Alle akzeptieren',
                'acceptNecessary' => 'Alle ablehnen',
                'showPreferences' => 'Einstellungen verwalten',
                'preferencesTitle' => 'Einstellungen konfigurieren',
                'preferencesUsageTitle' => 'Cookie-Nutzung',
                'preferencesUsageDescription' => 'Wir verwenden Cookies und ähnliche Technologien. Unbedingt erforderliche Cookies sind immer aktiv, da sie für den Betrieb der Website notwendig sind (Sicherheit, Sitzung, Speicherung der Einwilligung). Analyse-, Marketing- und Präferenz-Cookies sind optional und werden nur mit Ihrer Einwilligung gemäß Art. 6 Abs. 1 lit. a DSGVO aktiviert. Sie können alle akzeptieren, nicht erforderliche ablehnen oder Kategorien anpassen. Die Einwilligung können Sie jederzeit über „Cookie-Einstellungen“ widerrufen.',
                'save' => 'Einstellungen speichern',
                'privacyRoute' => 'demo_cookie_policy',
            ],
            [
                'locale' => 'pt',
                'title' => 'Preferências de cookies',
                'intro' => 'Utilizamos cookies e tecnologias semelhantes. Os cookies estritamente necessários estão sempre ativos porque são indispensáveis ao funcionamento do site (segurança, sessão, armazenamento do consentimento). Os cookies de análise, marketing e preferências são opcionais e só são ativados com o seu consentimento nos termos do art. 6.º, n.º 1, al. a) do RGPD. Pode aceitar todos, rejeitar os não essenciais ou personalizar as categorias. Pode retirar o consentimento a qualquer momento em «Definições de cookies».',
                'readMoreLabel' => 'Consultar a política de cookies',
                'acceptAll' => 'Aceitar todas',
                'acceptNecessary' => 'Rejeitar todas',
                'showPreferences' => 'Gerir preferências',
                'preferencesTitle' => 'Configurar preferências',
                'preferencesUsageTitle' => 'Utilização de cookies',
                'preferencesUsageDescription' => 'Utilizamos cookies e tecnologias semelhantes. Os cookies estritamente necessários estão sempre ativos porque são indispensáveis ao funcionamento do site (segurança, sessão, armazenamento do consentimento). Os cookies de análise, marketing e preferências são opcionais e só são ativados com o seu consentimento nos termos do art. 6.º, n.º 1, al. a) do RGPD. Pode aceitar todos, rejeitar os não essenciais ou personalizar as categorias. Pode retirar o consentimento a qualquer momento em «Definições de cookies».',
                'save' => 'Guardar preferências',
                'privacyRoute' => 'demo_cookie_policy',
            ],
            [
                'locale' => 'nl',
                'title' => 'Cookievoorkeuren',
                'intro' => 'Wij gebruiken cookies en vergelijkbare technologieën. Strikt noodzakelijke cookies zijn altijd actief omdat ze nodig zijn voor het functioneren van de site (beveiliging, sessie, opslag van toestemming). Analyse-, marketing- en voorkeurscookies zijn optioneel en worden alleen geactiveerd met uw toestemming op grond van art. 6 lid 1 onder a AVG. U kunt alles accepteren, niet-essentiële cookies weigeren of categorieën aanpassen. U kunt uw toestemming te allen tijde intrekken via «Cookie-instellingen».',
                'readMoreLabel' => 'Cookiebeleid lezen',
                'acceptAll' => 'Alles accepteren',
                'acceptNecessary' => 'Alles weigeren',
                'showPreferences' => 'Voorkeuren beheren',
                'preferencesTitle' => 'Voorkeuren configureren',
                'preferencesUsageTitle' => 'Cookiegebruik',
                'preferencesUsageDescription' => 'Wij gebruiken cookies en vergelijkbare technologieën. Strikt noodzakelijke cookies zijn altijd actief omdat ze nodig zijn voor het functioneren van de site (beveiliging, sessie, opslag van toestemming). Analyse-, marketing- en voorkeurscookies zijn optioneel en worden alleen geactiveerd met uw toestemming op grond van art. 6 lid 1 onder a AVG. U kunt alles accepteren, niet-essentiële cookies weigeren of categorieën aanpassen. U kunt uw toestemming te allen tijde intrekken via «Cookie-instellingen».',
                'save' => 'Voorkeuren opslaan',
                'privacyRoute' => 'demo_cookie_policy',
            ],
            [
                'locale' => 'pl',
                'title' => 'Preferencje plików cookie',
                'intro' => 'Używamy plików cookie i podobnych technologii. Ściśle niezbędne pliki cookie są zawsze aktywne, ponieważ są wymagane do działania witryny (bezpieczeństwo, sesja, przechowywanie zgody). Pliki analityczne, marketingowe i preferencyjne są opcjonalne i aktywowane wyłącznie za Twoją zgodą na podstawie art. 6 ust. 1 lit. a RODO. Możesz zaakceptować wszystkie, odrzucić nieistotne lub dostosować kategorie. Zgodę możesz wycofać w dowolnym momencie w «Ustawieniach plików cookie».',
                'readMoreLabel' => 'Polityka plików cookie',
                'acceptAll' => 'Zaakceptuj wszystkie',
                'acceptNecessary' => 'Odrzuć wszystkie',
                'showPreferences' => 'Zarządzaj preferencjami',
                'preferencesTitle' => 'Skonfiguruj preferencje',
                'preferencesUsageTitle' => 'Korzystanie z plików cookie',
                'preferencesUsageDescription' => 'Używamy plików cookie i podobnych technologii. Ściśle niezbędne pliki cookie są zawsze aktywne, ponieważ są wymagane do działania witryny (bezpieczeństwo, sesja, przechowywanie zgody). Pliki analityczne, marketingowe i preferencyjne są opcjonalne i aktywowane wyłącznie za Twoją zgodą na podstawie art. 6 ust. 1 lit. a RODO. Możesz zaakceptować wszystkie, odrzucić nieistotne lub dostosować kategorie. Zgodę możesz wycofać w dowolnym momencie w «Ustawieniach plików cookie».',
                'save' => 'Zapisz preferencje',
                'privacyRoute' => 'demo_cookie_policy',
            ],
            [
                'locale' => 'ca',
                'title' => 'Preferències de galetes',
                'intro' => 'Utilitzem galetes i tecnologies similars. Les galetes estrictament necessàries estan sempre actives perquè són imprescindibles per al funcionament del lloc (seguretat, sessió, emmagatzematge del consentiment). Les galetes d’anàlisi, màrqueting i preferències són opcionals i només s’activen amb el vostre consentiment d’acord amb l’art. 6.1.a) del RGPD. Podeu acceptar-les totes, rebutjar les no essencials o personalitzar les categories. Podeu retirar el consentiment en qualsevol moment des de «Configuració de galetes».',
                'readMoreLabel' => 'Consultar la política de galetes',
                'acceptAll' => 'Acceptar-les totes',
                'acceptNecessary' => 'Rebutjar-les totes',
                'showPreferences' => 'Gestionar preferències',
                'preferencesTitle' => 'Configura les teves preferències',
                'preferencesUsageTitle' => 'Ús de galetes',
                'preferencesUsageDescription' => 'Utilitzem galetes i tecnologies similars. Les galetes estrictament necessàries estan sempre actives perquè són imprescindibles per al funcionament del lloc (seguretat, sessió, emmagatzematge del consentiment). Les galetes d’anàlisi, màrqueting i preferències són opcionals i només s’activen amb el vostre consentiment d’acord amb l’art. 6.1.a) del RGPD. Podeu acceptar-les totes, rebutjar les no essencials o personalitzar les categories. Podeu retirar el consentiment en qualsevol moment des de «Configuració de galetes».',
                'save' => 'Desar preferències',
                'privacyRoute' => 'demo_cookie_policy',
            ],
        ];
    }
}
