<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;

/**
 * Canonical multilingual cookie inventory for demo applications.
 */
final class DemoCookieCatalog
{
    /** @var list<string> */
    private const LOCALES = DemoLocale::ALL;

    /**
     * @return list<array{
     *     name: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     sortOrder: int,
     *     translations: array<string, array{provider: string, purpose: string}>
     * }>
     */
    public static function cookies(): array
    {
        return [
            self::cookie(
                CookieNameEnum::COOKIE_CONSENT_NAME,
                '12 months',
                'required',
                CookieDefinition::TYPE_FIRST_PARTY,
                0,
                [
                    'en' => ['This site', 'Records your cookie consent choices (accepted, rejected or customised categories) so we do not ask again on every visit. Legal basis: Article 6(1)(c) GDPR (compliance with legal obligations).'],
                    'es' => ['Este sitio', 'Registra sus decisiones de consentimiento de cookies (aceptadas, rechazadas o categorías personalizadas) para no volver a solicitarlas en cada visita. Base legal: artículo 6.1.c) del RGPD (cumplimiento de obligaciones legales).'],
                    'it' => ['Questo sito', 'Registra le sue scelte di consenso sui cookie (accettati, rifiutati o categorie personalizzate) per non richiederle a ogni visita. Base giuridica: art. 6(1)(c) GDPR (adempimento di obblighi legali).'],
                    'fr' => ['Ce site', 'Enregistre vos choix de consentement aux cookies (acceptés, refusés ou catégories personnalisées) afin de ne pas vous les redemander à chaque visite. Base légale : art. 6(1)(c) RGPD (respect d’obligations légales).'],
                    'de' => ['Diese Website', 'Speichert Ihre Cookie-Einwilligungsentscheidungen (akzeptiert, abgelehnt oder angepasste Kategorien), damit wir nicht bei jedem Besuch erneut fragen müssen. Rechtsgrundlage: Art. 6 Abs. 1 lit. c DSGVO (Erfüllung rechtlicher Pflichten).'],
                    'pt' => ['Este site', 'Regista as suas escolhas de consentimento de cookies (aceites, rejeitadas ou categorias personalizadas) para não voltarmos a solicitá-las em cada visita. Base legal: art. 6.º, n.º 1, al. c) do RGPD (cumprimento de obrigações legais).'],
                    'nl' => ['Deze site', 'Slaat uw cookievoorkeuren op (geaccepteerd, geweigerd of aangepaste categorieën) zodat we dit niet bij elk bezoek opnieuw hoeven te vragen. Rechtsgrond: art. 6 lid 1 onder c AVG (naleving van wettelijke verplichtingen).'],
                    'pl' => ['Ta witryna', 'Zapisuje Twoje wybory dotyczące zgody na pliki cookie (zaakceptowane, odrzucone lub dostosowane kategorie), aby nie pytać ponownie przy każdej wizycie. Podstawa prawna: art. 6 ust. 1 lit. c RODO (wypełnienie obowiązków prawnych).'],
                    'ca' => ['Aquest lloc', 'Registra les vostres decisions de consentiment de galetes (acceptades, rebutjades o categories personalitzades) per no tornar-les a sol·licitar a cada visita. Base legal: art. 6.1.c) del RGPD (compliment d’obligacions legals).'],
                ],
            ),
            self::cookie(
                CookieNameEnum::COOKIE_CONSENT_KEY_NAME,
                '12 months',
                'required',
                CookieDefinition::TYPE_FIRST_PARTY,
                1,
                [
                    'en' => ['This site', 'Stores an anonymous technical identifier that links your browser consent record to the audit log maintained for GDPR accountability purposes.'],
                    'es' => ['Este sitio', 'Almacena un identificador técnico anónimo que vincula el registro de consentimiento de su navegador con el registro de auditoría mantenido con fines de responsabilidad proactiva conforme al RGPD.'],
                    'it' => ['Questo sito', 'Memorizza un identificatore tecnico anonimo che collega il record di consenso del browser al registro di audit mantenuto ai fini della responsabilità GDPR.'],
                    'fr' => ['Ce site', 'Stocke un identifiant technique anonyme qui relie l’enregistrement de consentement de votre navigateur au journal d’audit tenu à des fins de responsabilité RGPD.'],
                    'de' => ['Diese Website', 'Speichert eine anonyme technische Kennung, die Ihren Browser-Einwilligungsdatensatz mit dem zu GDPR-Rechenschaftszwecken geführten Audit-Protokoll verknüpft.'],
                    'pt' => ['Este site', 'Armazena um identificador técnico anónimo que associa o registo de consentimento do seu navegador ao registo de auditoria mantido para efeitos de responsabilização ao abrigo do RGPD.'],
                    'nl' => ['Deze site', 'Slaat een anonieme technische identifier op die uw browser-toestemmingsrecord koppelt aan het auditlogboek dat wordt bijgehouden voor GDPR-verantwoording.'],
                    'pl' => ['Ta witryna', 'Przechowuje anonimowy identyfikator techniczny łączący zapis zgody w przeglądarce z dziennikiem audytu prowadzonym w celach rozliczalności RODO.'],
                    'ca' => ['Aquest lloc', 'Emmagatzema un identificador tècnic anònim que vincula el registre de consentiment del navegador amb el registre d’auditoria mantingut amb finalitats de responsabilitat RGPD.'],
                ],
            ),
            self::cookie(
                'PHPSESSID',
                'Session',
                'required',
                CookieDefinition::TYPE_FIRST_PARTY,
                2,
                [
                    'en' => ['This site', 'Maintains your authenticated browsing session and preserves form data while you navigate between pages. Deleted when you close the browser.'],
                    'es' => ['Este sitio', 'Mantiene su sesión de navegación autenticada y conserva los datos de formularios mientras navega entre páginas. Se elimina al cerrar el navegador.'],
                    'it' => ['Questo sito', 'Mantiene la sessione di navigazione autenticata e conserva i dati dei moduli durante la navigazione tra le pagine. Viene eliminato alla chiusura del browser.'],
                    'fr' => ['Ce site', 'Maintient votre session de navigation authentifiée et conserve les données de formulaire pendant votre navigation. Supprimé à la fermeture du navigateur.'],
                    'de' => ['Diese Website', 'Erhält Ihre authentifizierte Browsersitzung aufrecht und bewahrt Formulardaten während der Navigation zwischen Seiten. Wird beim Schließen des Browsers gelöscht.'],
                    'pt' => ['Este site', 'Mantém a sua sessão de navegação autenticada e preserva os dados de formulários enquanto navega entre páginas. Eliminado ao fechar o navegador.'],
                    'nl' => ['Deze site', 'Handhaaft uw geauthenticeerde browsersessie en bewaart formuliergegevens terwijl u tussen pagina’s navigeert. Verwijderd bij het sluiten van de browser.'],
                    'pl' => ['Ta witryna', 'Utrzymuje uwierzytelnioną sesję przeglądania i zachowuje dane formularzy podczas nawigacji między stronami. Usuwany po zamknięciu przeglądarki.'],
                    'ca' => ['Aquest lloc', 'Manté la sessió de navegació autenticada i conserva les dades dels formularis mentre navegueu entre pàgines. S’elimina en tancar el navegador.'],
                ],
            ),
            self::cookie(
                'sf_redirect',
                'Session',
                'required',
                CookieDefinition::TYPE_FIRST_PARTY,
                3,
                [
                    'en' => ['Symfony', 'Stores the intended return URL during internal redirects—for example after submitting a form—so you are sent back to the correct page.'],
                    'es' => ['Symfony', 'Almacena la URL de retorno prevista durante redirecciones internas—por ejemplo, tras enviar un formulario—para devolverle a la página correcta.'],
                    'it' => ['Symfony', 'Memorizza l’URL di ritorno previsto durante i reindirizzamenti interni—ad esempio dopo l’invio di un modulo—per riportarla alla pagina corretta.'],
                    'fr' => ['Symfony', 'Stocke l’URL de retour prévue lors des redirections internes—par exemple après l’envoi d’un formulaire—afin de vous renvoyer vers la bonne page.'],
                    'de' => ['Symfony', 'Speichert die vorgesehene Rückgabe-URL bei internen Weiterleitungen—z. B. nach dem Absenden eines Formulars—damit Sie zur richtigen Seite zurückkehren.'],
                    'pt' => ['Symfony', 'Armazena o URL de retorno pretendido durante redirecionamentos internos—por exemplo, após submeter um formulário—para o encaminhar à página correta.'],
                    'nl' => ['Symfony', 'Slaat de beoogde terugkeer-URL op tijdens interne omleidingen—bijvoorbeeld na het verzenden van een formulier—zodat u naar de juiste pagina wordt teruggestuurd.'],
                    'pl' => ['Symfony', 'Przechowuje docelowy adres URL powrotu podczas wewnętrznych przekierowań—np. po wysłaniu formularza—aby przekierować Cię na właściwą stronę.'],
                    'ca' => ['Symfony', 'Emmagatzema l’URL de retorn previst durant redireccions internes—per exemple, després d’enviar un formulari—per retornar-vos a la pàgina correcta.'],
                ],
            ),
            self::cookie(
                'demo_sidebar',
                '6 months',
                'functionality',
                CookieDefinition::TYPE_FIRST_PARTY,
                10,
                [
                    'en' => ['This site', 'Remembers whether the demo navigation sidebar is expanded or collapsed so your layout preference persists across visits.'],
                    'es' => ['Este sitio', 'Recuerda si la barra lateral de navegación del demo está expandida o contraída para mantener su preferencia de diseño entre visitas.'],
                    'it' => ['Questo sito', 'Ricorda se la barra laterale di navigazione demo è espansa o compressa per mantenere la preferenza di layout tra le visite.'],
                    'fr' => ['Ce site', 'Mémorise si la barre latérale de navigation de la démo est développée ou réduite afin de conserver votre préférence d’affichage entre les visites.'],
                    'de' => ['Diese Website', 'Merkt sich, ob die Demo-Navigationsleiste ausgeklappt oder eingeklappt ist, damit Ihre Layout-Präferenz über Besuche hinweg erhalten bleibt.'],
                    'pt' => ['Este site', 'Memoriza se a barra lateral de navegação da demo está expandida ou recolhida para manter a sua preferência de layout entre visitas.'],
                    'nl' => ['Deze site', 'Onthoudt of de demo-navigatiezijbalk is uitgevouwen of ingeklapt, zodat uw lay-outvoorkeur bij volgende bezoeken behouden blijft.'],
                    'pl' => ['Ta witryna', 'Zapamiętuje, czy pasek boczny nawigacji demo jest rozwinięty czy zwinięty, aby zachować preferencje układu między wizytami.'],
                    'ca' => ['Aquest lloc', 'Recorda si la barra lateral de navegació de la demo està expandida o replegada per mantenir la preferència de disseny entre visites.'],
                ],
            ),
            self::cookie(
                'symfony_csrf',
                'Session',
                'functionality',
                CookieDefinition::TYPE_FIRST_PARTY,
                11,
                [
                    'en' => ['Symfony', 'Contains a cross-site request forgery (CSRF) token that validates form submissions originate from this website and protects against unauthorised actions.'],
                    'es' => ['Symfony', 'Contiene un token de protección contra falsificación de peticiones en sitios cruzados (CSRF) que valida que los envíos de formularios proceden de este sitio web y protege contra acciones no autorizadas.'],
                    'it' => ['Symfony', 'Contiene un token di protezione CSRF (Cross-Site Request Forgery) che verifica che gli invii di moduli provengano da questo sito e protegge da azioni non autorizzate.'],
                    'fr' => ['Symfony', 'Contient un jeton de protection CSRF (Cross-Site Request Forgery) qui vérifie que les soumissions de formulaires proviennent de ce site et protège contre les actions non autorisées.'],
                    'de' => ['Symfony', 'Enthält ein CSRF-Token (Cross-Site Request Forgery), das prüft, ob Formularübermittlungen von dieser Website stammen, und vor unbefugten Aktionen schützt.'],
                    'pt' => ['Symfony', 'Contém um token de proteção CSRF (Cross-Site Request Forgery) que valida se os envios de formulários provêm deste site e protege contra ações não autorizadas.'],
                    'nl' => ['Symfony', 'Bevat een CSRF-token (Cross-Site Request Forgery) dat controleert of formulierinzendingen van deze website afkomstig zijn en beschermt tegen ongeautoriseerde acties.'],
                    'pl' => ['Symfony', 'Zawiera token CSRF (Cross-Site Request Forgery), który weryfikuje, czy przesyłane formularze pochodzą z tej witryny, i chroni przed nieautoryzowanymi działaniami.'],
                    'ca' => ['Symfony', 'Conté un token de protecció CSRF (Cross-Site Request Forgery) que valida que els enviaments de formularis procedeixen d’aquest lloc web i protegeix contra accions no autoritzades.'],
                ],
            ),
            self::cookie(
                'demo_locale',
                '12 months',
                CategoryEnum::CATEGORY_PREFERENCES,
                CookieDefinition::TYPE_FIRST_PARTY,
                20,
                [
                    'en' => ['This site', 'Stores your selected interface language so pages, labels and legal notices are displayed in your preferred locale.'],
                    'es' => ['Este sitio', 'Almacena el idioma de interfaz seleccionado para mostrar páginas, etiquetas y avisos legales en su configuración regional preferida.'],
                    'it' => ['Questo sito', 'Memorizza la lingua dell’interfaccia selezionata per visualizzare pagine, etichette e informative legali nella locale preferita.'],
                    'fr' => ['Ce site', 'Enregistre la langue d’interface sélectionnée afin d’afficher pages, libellés et mentions légales dans votre locale préférée.'],
                    'de' => ['Diese Website', 'Speichert Ihre gewählte Oberflächensprache, damit Seiten, Beschriftungen und Rechtshinweise in Ihrer bevorzugten Locale angezeigt werden.'],
                    'pt' => ['Este site', 'Armazena o idioma de interface selecionado para apresentar páginas, rótulos e avisos legais na sua locale preferida.'],
                    'nl' => ['Deze site', 'Slaat uw gekozen interfacetaal op zodat pagina’s, labels en juridische mededelingen in uw voorkeurstaal worden weergegeven.'],
                    'pl' => ['Ta witryna', 'Przechowuje wybrany język interfejsu, aby strony, etykiety i informacje prawne były wyświetlane w preferowanej lokalizacji.'],
                    'ca' => ['Aquest lloc', 'Emmagatzema l’idioma d’interfície seleccionat per mostrar pàgines, etiquetes i avisos legals en la vostra configuració regional preferida.'],
                ],
            ),
            self::cookie(
                '_ga',
                '2 years',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                30,
                [
                    'en' => ['Google Analytics', 'Registers a unique client identifier used to distinguish users and compile aggregated statistics on site usage. Data may be transferred to the United States under Standard Contractual Clauses.'],
                    'es' => ['Google Analytics', 'Registra un identificador único de cliente para distinguir usuarios y elaborar estadísticas agregadas sobre el uso del sitio. Los datos pueden transferirse a Estados Unidos bajo cláusulas contractuales tipo.'],
                    'it' => ['Google Analytics', 'Registra un identificatore univoco del client per distinguere gli utenti e compilare statistiche aggregate sull’utilizzo del sito. I dati possono essere trasferiti negli Stati Uniti con clausole contrattuali standard.'],
                    'fr' => ['Google Analytics', 'Enregistre un identifiant client unique pour distinguer les utilisateurs et produire des statistiques agrégées sur l’utilisation du site. Les données peuvent être transférées aux États-Unis sous clauses contractuelles types.'],
                    'de' => ['Google Analytics', 'Registriert eine eindeutige Client-ID zur Unterscheidung von Nutzern und Erstellung aggregierter Nutzungsstatistiken. Daten können unter Standardvertragsklauseln in die USA übermittelt werden.'],
                    'pt' => ['Google Analytics', 'Regista um identificador único de cliente para distinguir utilizadores e compilar estatísticas agregadas de utilização do site. Os dados podem ser transferidos para os EUA ao abrigo de cláusulas contratuais-tipo.'],
                    'nl' => ['Google Analytics', 'Registreert een unieke client-ID om gebruikers te onderscheiden en geaggregeerde gebruiksstatistieken samen te stellen. Gegevens kunnen worden overgedragen naar de VS onder standaardcontractbepalingen.'],
                    'pl' => ['Google Analytics', 'Rejestruje unikalny identyfikator klienta w celu rozróżniania użytkowników i tworzenia zagregowanych statystyk korzystania z witryny. Dane mogą być przekazywane do USA na podstawie standardowych klauzul umownych.'],
                    'ca' => ['Google Analytics', 'Registra un identificador únic de client per distingir usuaris i elaborar estadístiques agregades d’ús del lloc. Les dades es poden transferir als Estats Units sota clàusules contractuals tipus.'],
                ],
            ),
            self::cookie(
                '_gid',
                '24 hours',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                31,
                [
                    'en' => ['Google Analytics', 'Distinguishes users within a 24-hour period to measure daily visit volumes and session activity without long-term profiling.'],
                    'es' => ['Google Analytics', 'Distingue usuarios en un periodo de 24 horas para medir volúmenes de visitas diarias y actividad de sesión sin elaborar perfiles a largo plazo.'],
                    'it' => ['Google Analytics', 'Distingue gli utenti in un periodo di 24 ore per misurare i volumi di visite giornaliere e l’attività di sessione senza profilazione a lungo termine.'],
                    'fr' => ['Google Analytics', 'Distingue les utilisateurs sur une période de 24 heures pour mesurer les volumes de visites quotidiennes et l’activité de session sans profilage à long terme.'],
                    'de' => ['Google Analytics', 'Unterscheidet Nutzer innerhalb von 24 Stunden, um tägliche Besuchsvolumina und Sitzungsaktivität zu messen, ohne langfristiges Profiling.'],
                    'pt' => ['Google Analytics', 'Distingue utilizadores num período de 24 horas para medir volumes de visitas diárias e atividade de sessão sem criação de perfis a longo prazo.'],
                    'nl' => ['Google Analytics', 'Onderscheidt gebruikers binnen een periode van 24 uur om dagelijkse bezoekvolumes en sessieactiviteit te meten zonder langdurige profilering.'],
                    'pl' => ['Google Analytics', 'Rozróżnia użytkowników w okresie 24 godzin w celu pomiaru dziennych wolumenów wizyt i aktywności sesji bez długoterminowego profilowania.'],
                    'ca' => ['Google Analytics', 'Distingeix usuaris en un període de 24 hores per mesurar volums de visites diàries i activitat de sessió sense perfilat a llarg termini.'],
                ],
            ),
            self::cookie(
                '_gat',
                '1 minute',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                32,
                [
                    'en' => ['Google Analytics', 'Throttles the request rate to Google Analytics servers to limit data collection frequency and reduce server load.'],
                    'es' => ['Google Analytics', 'Limita la frecuencia de solicitudes a los servidores de Google Analytics para restringir la recopilación de datos y reducir la carga del servidor.'],
                    'it' => ['Google Analytics', 'Limita la frequenza delle richieste ai server di Google Analytics per ridurre la raccolta dati e il carico sui server.'],
                    'fr' => ['Google Analytics', 'Limite le débit de requêtes vers les serveurs Google Analytics afin de restreindre la collecte de données et réduire la charge serveur.'],
                    'de' => ['Google Analytics', 'Drosselt die Anfragerate an Google-Analytics-Server, um die Datenerhebungsfrequenz zu begrenzen und die Serverlast zu reduzieren.'],
                    'pt' => ['Google Analytics', 'Limita a taxa de pedidos aos servidores do Google Analytics para restringir a recolha de dados e reduzir a carga do servidor.'],
                    'nl' => ['Google Analytics', 'Beperkt het verzoektempo naar Google Analytics-servers om de frequentie van gegevensverzameling te beperken en de serverbelasting te verlagen.'],
                    'pl' => ['Google Analytics', 'Ogranicza częstotliwość żądań do serwerów Google Analytics, aby ograniczyć zbieranie danych i obciążenie serwera.'],
                    'ca' => ['Google Analytics', 'Limita la freqüència de sol·licituds als servidors de Google Analytics per restringir la recollida de dades i reduir la càrrega del servidor.'],
                ],
            ),
            self::cookie(
                '_ga_demo',
                '2 years',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_FIRST_PARTY,
                33,
                [
                    'en' => ['Demo Analytics', 'First-party demo cookie that simulates anonymous page-view counting for illustration purposes. No data is shared with external analytics providers.'],
                    'es' => ['Demo Analytics', 'Cookie de demostración de origen propio que simula el recuento anónimo de páginas vistas con fines ilustrativos. No se comparten datos con proveedores analíticos externos.'],
                    'it' => ['Demo Analytics', 'Cookie demo di prima parte che simula il conteggio anonimo delle visualizzazioni di pagina a scopo dimostrativo. Nessun dato viene condiviso con fornitori analitici esterni.'],
                    'fr' => ['Demo Analytics', 'Cookie de démonstration first-party simulant un comptage anonyme de pages vues à titre illustratif. Aucune donnée n’est partagée avec des prestataires d’analyse externes.'],
                    'de' => ['Demo Analytics', 'First-Party-Demo-Cookie, das anonyme Seitenaufrufzählungen zu Demonstrationszwecken simuliert. Es werden keine Daten an externe Analyseanbieter weitergegeben.'],
                    'pt' => ['Demo Analytics', 'Cookie de demonstração first-party que simula a contagem anónima de visualizações de página para fins ilustrativos. Nenhum dado é partilhado com fornecedores analíticos externos.'],
                    'nl' => ['Demo Analytics', 'First-party democookie die anonieme paginaweergaven simuleert ter illustratie. Er worden geen gegevens gedeeld met externe analyseproviders.'],
                    'pl' => ['Demo Analytics', 'Plik cookie demo first-party symulujący anonimowe liczenie odsłon stron w celach demonstracyjnych. Żadne dane nie są udostępniane zewnętrznym dostawcom analityki.'],
                    'ca' => ['Demo Analytics', 'Galeta de demostració first-party que simula el recompte anònim de pàgines vistes amb finalitats il·lustratives. No es comparteixen dades amb proveïdors analítics externs.'],
                ],
            ),
            self::cookie(
                '_fbp',
                '3 months',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                40,
                [
                    'en' => ['Meta (Facebook)', 'Identifies browsers across websites to deliver, measure and optimise advertising campaigns on Meta platforms. Activated only with your consent under Article 6(1)(a) GDPR.'],
                    'es' => ['Meta (Facebook)', 'Identifica navegadores en distintos sitios web para mostrar, medir y optimizar campañas publicitarias en las plataformas de Meta. Solo se activa con su consentimiento conforme al artículo 6.1.a) del RGPD.'],
                    'it' => ['Meta (Facebook)', 'Identifica i browser su diversi siti web per erogare, misurare e ottimizzare campagne pubblicitarie sulle piattaforme Meta. Attivato solo con il suo consenso ai sensi dell’art. 6(1)(a) GDPR.'],
                    'fr' => ['Meta (Facebook)', 'Identifie les navigateurs sur différents sites pour diffuser, mesurer et optimiser des campagnes publicitaires sur les plateformes Meta. Activé uniquement avec votre consentement au titre de l’art. 6(1)(a) RGPD.'],
                    'de' => ['Meta (Facebook)', 'Identifiziert Browser websiteübergreifend, um Werbekampagnen auf Meta-Plattformen auszuliefern, zu messen und zu optimieren. Aktivierung nur mit Einwilligung gemäß Art. 6 Abs. 1 lit. a DSGVO.'],
                    'pt' => ['Meta (Facebook)', 'Identifica navegadores em diferentes sites para veicular, medir e otimizar campanhas publicitárias nas plataformas Meta. Ativado apenas com o seu consentimento nos termos do art. 6.º, n.º 1, al. a) do RGPD.'],
                    'nl' => ['Meta (Facebook)', 'Identificeert browsers op verschillende websites om advertentiecampagnes op Meta-platforms te leveren, meten en optimaliseren. Alleen geactiveerd met uw toestemming op grond van art. 6 lid 1 onder a AVG.'],
                    'pl' => ['Meta (Facebook)', 'Identyfikuje przeglądarki w różnych witrynach w celu dostarczania, pomiaru i optymalizacji kampanii reklamowych na platformach Meta. Aktywowany wyłącznie za Twoją zgodą na podstawie art. 6 ust. 1 lit. a RODO.'],
                    'ca' => ['Meta (Facebook)', 'Identifica navegadors en diferents llocs web per mostrar, mesurar i optimitzar campanyes publicitàries a les plataformes Meta. Només s’activa amb el vostre consentiment d’acord amb l’art. 6.1.a) del RGPD.'],
                ],
            ),
            self::cookie(
                '_fbc',
                '3 months',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                41,
                [
                    'en' => ['Meta (Facebook)', 'Stores the last Facebook click identifier (fbclid) when you arrive from a Meta advertisement, enabling conversion attribution and campaign performance reporting.'],
                    'es' => ['Meta (Facebook)', 'Almacena el último identificador de clic de Facebook (fbclid) cuando accede desde un anuncio de Meta, permitiendo la atribución de conversiones y la elaboración de informes de rendimiento de campañas.'],
                    'it' => ['Meta (Facebook)', 'Memorizza l’ultimo identificatore di clic Facebook (fbclid) quando arriva da un annuncio Meta, consentendo l’attribuzione delle conversioni e i report sulle prestazioni delle campagne.'],
                    'fr' => ['Meta (Facebook)', 'Stocke le dernier identifiant de clic Facebook (fbclid) lorsque vous arrivez depuis une publicité Meta, permettant l’attribution des conversions et le reporting de performance des campagnes.'],
                    'de' => ['Meta (Facebook)', 'Speichert die letzte Facebook-Klick-ID (fbclid), wenn Sie über eine Meta-Anzeige gelangen, und ermöglicht Conversion-Attribution sowie Kampagnen-Performance-Berichte.'],
                    'pt' => ['Meta (Facebook)', 'Armazena o último identificador de clique do Facebook (fbclid) quando acede a partir de um anúncio Meta, permitindo a atribuição de conversões e relatórios de desempenho de campanhas.'],
                    'nl' => ['Meta (Facebook)', 'Slaat de laatste Facebook-klikidentifier (fbclid) op wanneer u via een Meta-advertentie binnenkomt, voor conversie-attributie en campagneprestatierapportage.'],
                    'pl' => ['Meta (Facebook)', 'Przechowuje ostatni identyfikator kliknięcia Facebook (fbclid), gdy trafiasz z reklamy Meta, umożliwiając atrybucję konwersji i raportowanie skuteczności kampanii.'],
                    'ca' => ['Meta (Facebook)', 'Emmagatzema l’últim identificador de clic de Facebook (fbclid) quan arribeu des d’un anunci de Meta, permetent l’atribució de conversions i informes de rendiment de campanyes.'],
                ],
            ),
            self::cookie(
                'IDE',
                '1 year',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                42,
                [
                    'en' => ['Google / DoubleClick', 'Registers ad impressions, user interactions and conversions to measure the effectiveness of display and video advertising across the Google Marketing Platform.'],
                    'es' => ['Google / DoubleClick', 'Registra impresiones publicitarias, interacciones del usuario y conversiones para medir la eficacia de la publicidad display y de vídeo en la plataforma Google Marketing.'],
                    'it' => ['Google / DoubleClick', 'Registra impressioni pubblicitarie, interazioni dell’utente e conversioni per misurare l’efficacia della pubblicità display e video sulla Google Marketing Platform.'],
                    'fr' => ['Google / DoubleClick', 'Enregistre les impressions publicitaires, les interactions utilisateur et les conversions pour mesurer l’efficacité de la publicité display et vidéo sur la Google Marketing Platform.'],
                    'de' => ['Google / DoubleClick', 'Registriert Anzeigenimpressionen, Nutzerinteraktionen und Conversions zur Messung der Wirksamkeit von Display- und Video-Werbung auf der Google Marketing Platform.'],
                    'pt' => ['Google / DoubleClick', 'Regista impressões publicitárias, interações do utilizador e conversões para medir a eficácia da publicidade display e de vídeo na Google Marketing Platform.'],
                    'nl' => ['Google / DoubleClick', 'Registreert advertentie-impressies, gebruikersinteracties en conversies om de effectiviteit van display- en videoadvertenties op het Google Marketing Platform te meten.'],
                    'pl' => ['Google / DoubleClick', 'Rejestruje wyświetlenia reklam, interakcje użytkownika i konwersje w celu pomiaru skuteczności reklam display i wideo w Google Marketing Platform.'],
                    'ca' => ['Google / DoubleClick', 'Registra impressions publicitàries, interaccions de l’usuari i conversions per mesurar l’eficàcia de la publicitat display i de vídeo a la Google Marketing Platform.'],
                ],
            ),
            self::cookie(
                'NID',
                '6 months',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                43,
                [
                    'en' => ['Google', 'Stores advertising preferences and personalisation signals used by Google services to tailor ads based on recent searches and interactions. May include profiling under Article 22 GDPR safeguards.'],
                    'es' => ['Google', 'Almacena preferencias publicitarias y señales de personalización utilizadas por los servicios de Google para adaptar anuncios según búsquedas e interacciones recientes. Puede implicar elaboración de perfiles con las garantías del artículo 22 del RGPD.'],
                    'it' => ['Google', 'Memorizza preferenze pubblicitarie e segnali di personalizzazione utilizzati dai servizi Google per adattare gli annunci in base a ricerche e interazioni recenti. Può comportare profilazione con le garanzie dell’art. 22 GDPR.'],
                    'fr' => ['Google', 'Stocke les préférences publicitaires et signaux de personnalisation utilisés par les services Google pour adapter les annonces selon les recherches et interactions récentes. Peut impliquer un profilage avec les garanties de l’art. 22 RGPD.'],
                    'de' => ['Google', 'Speichert Werbepräferenzen und Personalisierungssignale, die Google-Dienste nutzen, um Anzeigen anhand aktueller Suchen und Interaktionen anzupassen. Kann Profiling mit Schutzmechanismen gemäß Art. 22 DSGVO beinhalten.'],
                    'pt' => ['Google', 'Armazena preferências publicitárias e sinais de personalização utilizados pelos serviços Google para adaptar anúncios com base em pesquisas e interações recentes. Pode envolver criação de perfis com salvaguardas do art. 22.º do RGPD.'],
                    'nl' => ['Google', 'Slaat advertentievoorkeuren en personalisatiesignalen op die Google-diensten gebruiken om advertenties af te stemmen op recente zoekopdrachten en interacties. Kan profilering omvatten met waarborgen onder art. 22 AVG.'],
                    'pl' => ['Google', 'Przechowuje preferencje reklamowe i sygnały personalizacji wykorzystywane przez usługi Google do dopasowywania reklam na podstawie ostatnich wyszukiwań i interakcji. Może obejmować profilowanie z zabezpieczeniami art. 22 RODO.'],
                    'ca' => ['Google', 'Emmagatzema preferències publicitàries i senyals de personalització utilitzats pels serveis de Google per adaptar anuncis segons cerques i interaccions recents. Pot implicar perfilat amb les garanties de l’art. 22 del RGPD.'],
                ],
            ),
            self::cookie(
                '_fbp_demo',
                '3 months',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_FIRST_PARTY,
                44,
                [
                    'en' => ['Demo Ads', 'First-party demo placeholder that simulates remarketing audience segmentation and conversion tracking without contacting external ad networks.'],
                    'es' => ['Demo Ads', 'Marcador de demostración de origen propio que simula la segmentación de audiencias de remarketing y el seguimiento de conversiones sin contactar redes publicitarias externas.'],
                ],
            ),
            self::cookie(
                'REMEMBERME',
                '30 days',
                'required',
                CookieDefinition::TYPE_FIRST_PARTY,
                4,
                [
                    'en' => ['Symfony', 'Keeps you signed in on this device when you choose “Remember me” on login. Deleted when you log out or when the token expires.'],
                    'es' => ['Symfony', 'Mantiene su sesión iniciada en este dispositivo cuando elige «Recordarme» al acceder. Se elimina al cerrar sesión o al caducar el token.'],
                ],
            ),
            self::cookie(
                'XSRF-TOKEN',
                'Session',
                'required',
                CookieDefinition::TYPE_FIRST_PARTY,
                5,
                [
                    'en' => ['Symfony', 'Anti-CSRF token paired with the session to validate that state-changing requests originate from this application.'],
                    'es' => ['Symfony', 'Token anti-CSRF vinculado a la sesión para validar que las peticiones que modifican estado proceden de esta aplicación.'],
                ],
            ),
            self::cookie(
                'demo_chat_widget',
                '6 months',
                'functionality',
                CookieDefinition::TYPE_FIRST_PARTY,
                12,
                [
                    'en' => ['Demo Support', 'Remembers whether the optional support chat widget is open, minimised or dismissed so the interface state persists between visits.'],
                    'es' => ['Demo Soporte', 'Recuerda si el widget opcional de chat de soporte está abierto, minimizado o cerrado para mantener el estado de la interfaz entre visitas.'],
                ],
            ),
            self::cookie(
                '__cf_bm',
                '30 minutes',
                'functionality',
                CookieDefinition::TYPE_THIRD_PARTY,
                13,
                [
                    'en' => ['Cloudflare', 'Bot-management cookie used to distinguish humans from automated traffic and protect forms against abuse.'],
                    'es' => ['Cloudflare', 'Cookie de gestión de bots para distinguir tráfico humano de automatizado y proteger formularios frente a abusos.'],
                ],
            ),
            self::cookie(
                'vuid',
                '2 years',
                'functionality',
                CookieDefinition::TYPE_THIRD_PARTY,
                14,
                [
                    'en' => ['Vimeo', 'Stores embedded video player preferences such as volume and quality when a Vimeo iframe is loaded after consent.'],
                    'es' => ['Vimeo', 'Almacena preferencias del reproductor de vídeo incrustado (volumen, calidad) cuando se carga un iframe de Vimeo tras el consentimiento.'],
                ],
            ),
            self::cookie(
                'yt-remote-device-id',
                'Persistent',
                'functionality',
                CookieDefinition::TYPE_THIRD_PARTY,
                15,
                [
                    'en' => ['YouTube', 'Identifies the device for YouTube embedded players to restore playback settings and continue watching lists.'],
                    'es' => ['YouTube', 'Identifica el dispositivo para reproductores incrustados de YouTube y restaurar ajustes de reproducción y listas de continuar viendo.'],
                ],
            ),
            self::cookie(
                'demo_theme_mode',
                '12 months',
                CategoryEnum::CATEGORY_PREFERENCES,
                CookieDefinition::TYPE_FIRST_PARTY,
                21,
                [
                    'en' => ['This site', 'Stores your light/dark interface preference for the demo dashboard and legal pages.'],
                    'es' => ['Este sitio', 'Almacena su preferencia de interfaz clara/oscura para el panel demo y las páginas legales.'],
                ],
            ),
            self::cookie(
                'demo_currency',
                '12 months',
                CategoryEnum::CATEGORY_PREFERENCES,
                CookieDefinition::TYPE_FIRST_PARTY,
                22,
                [
                    'en' => ['This site', 'Remembers the currency you select when viewing illustrative pricing tables (EUR, USD, GBP).'],
                    'es' => ['Este sitio', 'Recuerda la moneda seleccionada al consultar tablas de precios ilustrativas (EUR, USD, GBP).'],
                ],
            ),
            self::cookie(
                'a11y_font_scale',
                '12 months',
                CategoryEnum::CATEGORY_PREFERENCES,
                CookieDefinition::TYPE_FIRST_PARTY,
                23,
                [
                    'en' => ['This site', 'Persists accessibility text scaling chosen in the demo accessibility toolbar.'],
                    'es' => ['Este sitio', 'Conserva el escalado de texto de accesibilidad elegido en la barra de herramientas demo.'],
                ],
            ),
            self::cookie(
                'demo_density',
                '12 months',
                CategoryEnum::CATEGORY_PREFERENCES,
                CookieDefinition::TYPE_FIRST_PARTY,
                24,
                [
                    'en' => ['This site', 'Stores compact or comfortable layout density for tables and navigation in the admin demo.'],
                    'es' => ['Este sitio', 'Almacena la densidad de diseño compacta o cómoda para tablas y navegación en el admin demo.'],
                ],
            ),
            self::cookie(
                '_pk_id',
                '13 months',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                34,
                [
                    'en' => ['Matomo', 'Anonymous visitor identifier used by Matomo to recognise returning users and compile traffic statistics.'],
                    'es' => ['Matomo', 'Identificador anónimo de visitante usado por Matomo para reconocer usuarios recurrentes y elaborar estadísticas de tráfico.'],
                ],
            ),
            self::cookie(
                '_pk_ses',
                '30 minutes',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                35,
                [
                    'en' => ['Matomo', 'Session cookie for Matomo that groups page views within a single visit.'],
                    'es' => ['Matomo', 'Cookie de sesión de Matomo que agrupa páginas vistas dentro de una misma visita.'],
                ],
            ),
            self::cookie(
                '_hjSessionUser',
                '1 year',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                36,
                [
                    'en' => ['Hotjar', 'Assigns a Hotjar user ID to analyse heatmaps, recordings and feedback widgets on allowed pages.'],
                    'es' => ['Hotjar', 'Asigna un ID de usuario Hotjar para analizar mapas de calor, grabaciones y widgets de feedback en páginas permitidas.'],
                ],
            ),
            self::cookie(
                '_clck',
                '1 year',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                37,
                [
                    'en' => ['Microsoft Clarity', 'Persists Clarity user ID to measure scroll depth, rage clicks and session replays.'],
                    'es' => ['Microsoft Clarity', 'Conserva el ID de usuario Clarity para medir profundidad de scroll, clics de frustración y repeticiones de sesión.'],
                ],
            ),
            self::cookie(
                '_gcl_au',
                '3 months',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                38,
                [
                    'en' => ['Google Ads', 'Used by Google Ads to experiment with ad efficiency across websites that use their services.'],
                    'es' => ['Google Ads', 'Utilizada por Google Ads para experimentar con la eficacia publicitaria en sitios que usan sus servicios.'],
                ],
            ),
            self::cookie(
                'AMP_TOKEN',
                'Session',
                CategoryEnum::CATEGORY_ANALYTICS,
                CookieDefinition::TYPE_THIRD_PARTY,
                39,
                [
                    'en' => ['Google Analytics', 'Temporary token that ensures analytics requests are sent only once per page load.'],
                    'es' => ['Google Analytics', 'Token temporal que garantiza que las peticiones analíticas se envían una sola vez por carga de página.'],
                ],
            ),
            self::cookie(
                'li_sugr',
                '3 months',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                45,
                [
                    'en' => ['LinkedIn', 'Browser identifier for LinkedIn Insight Tag used to measure conversions and build retargeting audiences.'],
                    'es' => ['LinkedIn', 'Identificador de navegador del Insight Tag de LinkedIn para medir conversiones y crear audiencias de retargeting.'],
                ],
            ),
            self::cookie(
                '_ttp',
                '13 months',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                46,
                [
                    'en' => ['TikTok', 'Tracks visits from TikTok ads to attribute conversions and optimise campaign delivery.'],
                    'es' => ['TikTok', 'Rastrea visitas desde anuncios de TikTok para atribuir conversiones y optimizar la entrega de campañas.'],
                ],
            ),
            self::cookie(
                'MUID',
                '1 year',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                47,
                [
                    'en' => ['Microsoft Advertising', 'Identifies browsers for Bing/Microsoft Ads remarketing and conversion measurement.'],
                    'es' => ['Microsoft Advertising', 'Identifica navegadores para remarketing y medición de conversiones en Bing/Microsoft Ads.'],
                ],
            ),
            self::cookie(
                'fr',
                '3 months',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                48,
                [
                    'en' => ['Meta (Facebook)', 'Contains encrypted Facebook user ID and login status for ad delivery and measurement across Meta pixels.'],
                    'es' => ['Meta (Facebook)', 'Contiene el ID de usuario cifrado de Facebook y el estado de sesión para entrega y medición publicitaria en píxeles Meta.'],
                ],
            ),
            self::cookie(
                'test_cookie',
                '15 minutes',
                CategoryEnum::CATEGORY_MARKETING,
                CookieDefinition::TYPE_THIRD_PARTY,
                49,
                [
                    'en' => ['Google / DoubleClick', 'Checks whether the browser accepts third-party marketing cookies before serving personalised ads.'],
                    'es' => ['Google / DoubleClick', 'Comprueba si el navegador acepta cookies publicitarias de terceros antes de servir anuncios personalizados.'],
                ],
            ),
        ];
    }

    /**
     * @param array<string, array{0: string, 1: string}> $rawTranslations locale => [provider, purpose]
     *
     * @return array{
     *     name: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     sortOrder: int,
     *     translations: array<string, array{provider: string, purpose: string}>
     * }
     */
    private static function cookie(
        string $name,
        string $duration,
        string $category,
        string $type,
        int $sortOrder,
        array $rawTranslations,
    ): array {
        $translations = [];

        foreach (self::LOCALES as $locale) {
            $entry = $rawTranslations[$locale] ?? $rawTranslations[DemoLocale::DEFAULT];
            $translations[$locale] = [
                'provider' => $entry[0],
                'purpose'  => $entry[1],
            ];
        }

        return [
            'name'         => $name,
            'duration'     => $duration,
            'category'     => $category,
            'type'         => $type,
            'sortOrder'    => $sortOrder,
            'translations' => $translations,
        ];
    }
}
