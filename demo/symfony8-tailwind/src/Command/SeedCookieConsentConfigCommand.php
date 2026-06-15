<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\DemoPlaygroundPreset;
use App\Demo\DemoPreferenceSections;
use App\Demo\GdprConsentCopy;
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
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('if-empty', null, InputOption::VALUE_NONE, 'Skip when all demo locales already have translations (unless --apply-playground is set)')
            ->addOption('apply-playground', null, InputOption::VALUE_NONE, 'Apply CookieConsent v3 playground-style UX defaults to the default profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io              = new SymfonyStyle($input, $output);
        $applyPlayground = (bool) $input->getOption('apply-playground');
        $defaults        = GdprConsentCopy::defaults();

        $config = $this->configRepository->findDefaultEnabled();

        if ($input->getOption('if-empty') && !$applyPlayground && $config !== null && $this->hasAllDefaultTranslations($config, $defaults)) {
            $io->writeln('<info>Cookie consent configuration already seeded.</info>');

            return Command::SUCCESS;
        }

        if ($config === null) {
            $config = (new CookieConsentConfig())
                ->setDefault(true)
                ->setEnabled(true)
                ->setName('Playground');
            $this->entityManager->persist($config);
            $applyPlayground = true;
        }

        if ($applyPlayground) {
            DemoPlaygroundPreset::applyTo($config);
        }

        $created = 0;
        $updated = 0;

        foreach ($defaults as $default) {
            $translation = $config->findTranslation($default['locale']);

            if (!$translation instanceof CookieConsentConfigTranslation) {
                $translation = (new CookieConsentConfigTranslation())->setLocale($default['locale']);
                $config->addTranslation($translation);
                $this->entityManager->persist($translation);
                ++$created;
            } elseif ($applyPlayground) {
                ++$updated;
            }

            $this->applyTranslationCopy($translation, $default, $applyPlayground);
        }

        $this->entityManager->flush();

        if ($applyPlayground) {
            $io->writeln('<info>Applied CookieConsent v3 playground preset (dark-turquoise, two-step modal, sections).</info>');
        }

        $io->writeln(sprintf('<info>Seeded %d translation(s), updated %d.</info>', $created, $updated));

        return Command::SUCCESS;
    }

    /**
     * @param list<array<string, string>> $defaults
     */
    private function hasAllDefaultTranslations(CookieConsentConfig $config, array $defaults): bool
    {
        foreach ($defaults as $default) {
            if ($config->findTranslation($default['locale']) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, string> $default
     */
    private function applyTranslationCopy(
        CookieConsentConfigTranslation $translation,
        array $default,
        bool $applyPlayground,
    ): void {
        $translation
            ->setConsentModalTitle($default['title'])
            ->setConsentModalDescription($default['intro'])
            ->setConsentModalFooter($default['readMoreLabel'])
            ->setConsentModalAcceptAllBtn($default['acceptAll'])
            ->setConsentModalAcceptNecessaryBtn($default['acceptNecessary'])
            ->setConsentModalShowPreferencesBtn($default['showPreferences'])
            ->setPreferencesModalTitle($default['preferencesTitle'])
            ->setPreferencesModalSavePreferencesBtn($default['save'])
            ->setPrivacyRoute($default['privacyRoute']);

        if ($applyPlayground) {
            $translation->setPreferenceSections(DemoPreferenceSections::forLocale($default['locale']));
        }
    }
}
