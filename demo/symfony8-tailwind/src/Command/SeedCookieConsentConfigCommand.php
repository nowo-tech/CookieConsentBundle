<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'demo:seed-cookie-consent-config',
    description: 'Seed default cookie consent configuration and translations for the demo locales.',
)]
final class SeedCookieConsentConfigCommand extends Command
{
    /**
     * @param list<array{
     *     locale: string,
     *     title: string,
     *     intro: string,
     *     readMoreLabel: string,
     *     acceptAll: string,
     *     acceptNecessary: string,
     *     save: string
     * }> $defaults
     */
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly array $defaults = [
            [
                'locale' => 'en',
                'title' => 'Cookie settings',
                'intro' => 'We use cookies to improve your experience.',
                'readMoreLabel' => 'Read our privacy policy',
                'acceptAll' => 'Allow all cookies',
                'acceptNecessary' => 'Only use functional cookies',
                'save' => 'Save settings',
            ],
            [
                'locale' => 'es',
                'title' => 'Configuración de cookies',
                'intro' => 'Usamos cookies para mejorar tu experiencia.',
                'readMoreLabel' => 'Leer la política de privacidad',
                'acceptAll' => 'Permitir todas las cookies',
                'acceptNecessary' => 'Solo cookies funcionales',
                'save' => 'Guardar configuración',
            ],
            [
                'locale' => 'it',
                'title' => 'Impostazioni cookie',
                'intro' => 'Utilizziamo i cookie per migliorare la tua esperienza.',
                'readMoreLabel' => 'Leggi la nostra informativa sulla privacy',
                'acceptAll' => 'Consenti tutti i cookie',
                'acceptNecessary' => 'Solo cookie funzionali',
                'save' => 'Salva impostazioni',
            ],
            [
                'locale' => 'fr',
                'title' => 'Paramètres des cookies',
                'intro' => 'Nous utilisons des cookies pour améliorer votre expérience.',
                'readMoreLabel' => 'Lire notre politique de confidentialité',
                'acceptAll' => 'Autoriser tous les cookies',
                'acceptNecessary' => 'Cookies fonctionnels uniquement',
                'save' => 'Enregistrer les paramètres',
            ],
            [
                'locale' => 'de',
                'title' => 'Cookie-Einstellungen',
                'intro' => 'Wir verwenden Cookies, um Ihre Erfahrung zu verbessern.',
                'readMoreLabel' => 'Unsere Datenschutzerklärung lesen',
                'acceptAll' => 'Alle Cookies erlauben',
                'acceptNecessary' => 'Nur funktionale Cookies',
                'save' => 'Einstellungen speichern',
            ],
            [
                'locale' => 'pt',
                'title' => 'Definições de cookies',
                'intro' => 'Utilizamos cookies para melhorar a sua experiência.',
                'readMoreLabel' => 'Ler a nossa política de privacidade',
                'acceptAll' => 'Permitir todos os cookies',
                'acceptNecessary' => 'Apenas cookies funcionais',
                'save' => 'Guardar definições',
            ],
            [
                'locale' => 'nl',
                'title' => 'Cookie-instellingen',
                'intro' => 'We gebruiken cookies om uw ervaring te verbeteren.',
                'readMoreLabel' => 'Lees ons privacybeleid',
                'acceptAll' => 'Alle cookies toestaan',
                'acceptNecessary' => 'Alleen functionele cookies',
                'save' => 'Instellingen opslaan',
            ],
            [
                'locale' => 'pl',
                'title' => 'Ustawienia plików cookie',
                'intro' => 'Używamy plików cookie, aby poprawić Twoje wrażenia.',
                'readMoreLabel' => 'Przeczytaj naszą politykę prywatności',
                'acceptAll' => 'Zezwól na wszystkie pliki cookie',
                'acceptNecessary' => 'Tylko pliki cookie funkcjonalne',
                'save' => 'Zapisz ustawienia',
            ],
            [
                'locale' => 'ca',
                'title' => 'Configuració de galetes',
                'intro' => 'Utilitzem galetes per millorar la vostra experiència.',
                'readMoreLabel' => 'Llegir la nostra política de privacitat',
                'acceptAll' => 'Permetre totes les galetes',
                'acceptNecessary' => 'Només galetes funcionals',
                'save' => 'Desar configuració',
            ],
        ],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('if-empty', null, InputOption::VALUE_NONE, 'Only seed when all demo locales already have translations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $input->getOption('if-empty') ? new SymfonyStyle($input, $output) : null;

        $config = $this->configRepository->findDefaultEnabled();

        if ($input->getOption('if-empty') && $config !== null && $this->hasAllDefaultTranslations($config)) {
            $io?->writeln('<info>Cookie consent configuration already seeded.</info>');

            return Command::SUCCESS;
        }

        if ($config === null) {
            $config = (new CookieConsentConfig())
                ->setDefault(true)
                ->setEnabled(true)
                ->setName('Default');
            $this->entityManager->persist($config);
        }

        $created = 0;

        foreach ($this->defaults as $default) {
            if ($config->findTranslation($default['locale']) !== null) {
                continue;
            }

            $translation = (new CookieConsentConfigTranslation())
                ->setLocale($default['locale'])
                ->setConsentModalTitle($default['title'])
                ->setConsentModalDescription($default['intro'])
                ->setConsentModalFooter($default['readMoreLabel'])
                ->setConsentModalAcceptAllBtn($default['acceptAll'])
                ->setConsentModalAcceptNecessaryBtn($default['acceptNecessary'])
                ->setPreferencesModalSavePreferencesBtn($default['save']);

            $config->addTranslation($translation);
            $this->entityManager->persist($translation);
            ++$created;
        }

        if ($created > 0 || $config->getId() === null) {
            $this->entityManager->flush();
        }

        $io?->writeln(sprintf('<info>Seeded %d cookie consent translation(s).</info>', $created));

        return Command::SUCCESS;
    }

    private function hasAllDefaultTranslations(CookieConsentConfig $config): bool
    {
        foreach ($this->defaults as $default) {
            if ($config->findTranslation($default['locale']) === null) {
                return false;
            }
        }

        return true;
    }
}
