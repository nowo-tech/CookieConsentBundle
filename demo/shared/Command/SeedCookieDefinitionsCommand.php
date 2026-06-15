<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\DemoCookieCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'demo:seed-cookie-definitions',
    description: 'Seed multilingual sample cookie inventory for the default demo consent profile.',
)]
final class SeedCookieDefinitionsCommand extends Command
{
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly CookieDefinitionRepository $definitionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('if-empty', null, InputOption::VALUE_NONE, 'Skip when the default profile already has cookie definitions')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Replace existing cookie definitions on the default profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $config = $this->configRepository->findDefaultEnabled();

        if (!$config instanceof CookieConsentConfig) {
            $io->error('No default enabled cookie consent profile found. Run demo:seed-cookie-consent-config first.');

            return Command::FAILURE;
        }

        if ($input->getOption('if-empty') && $this->definitionRepository->findByConfigOrdered($config) !== []) {
            $io->writeln('<info>Cookie inventory already seeded.</info>');

            return Command::SUCCESS;
        }

        if ($input->getOption('force')) {
            foreach ($this->definitionRepository->findByConfigOrdered($config) as $existing) {
                $this->entityManager->remove($existing);
            }

            $this->entityManager->flush();
        }

        $created = 0;

        foreach (DemoCookieCatalog::cookies() as $row) {
            $definition = $this->findOrCreateDefinition($config, (string) $row['name']);

            $definition
                ->setDuration((string) $row['duration'])
                ->setCategory((string) $row['category'])
                ->setType((string) $row['type'])
                ->setSortOrder((int) $row['sortOrder'])
                ->setAllowedByDefault((bool) ($row['allowedByDefault'] ?? !in_array($row['category'], ['analytics', 'marketing'], true)));

            foreach ($row['translations'] as $locale => $copy) {
                if (!is_array($copy)) {
                    continue;
                }

                $translation = $definition->findTranslation((string) $locale);

                if (!$translation instanceof CookieDefinitionTranslation) {
                    $translation = (new CookieDefinitionTranslation())->setLocale((string) $locale);
                    $definition->addTranslation($translation);
                    $this->entityManager->persist($translation);
                }

                $translation
                    ->setProvider((string) ($copy['provider'] ?? ''))
                    ->setPurpose((string) ($copy['purpose'] ?? ''));
            }

            if ($definition->getId() === null) {
                $config->addCookieDefinition($definition);
                $this->entityManager->persist($definition);
                ++$created;
            }
        }

        $this->entityManager->flush();

        $io->writeln(sprintf(
            '<info>Seeded %d cookie definition(s) with translations for %d locale(s).</info>',
            $created,
            count(DemoCookieCatalog::cookies()[0]['translations'] ?? []),
        ));

        return Command::SUCCESS;
    }

    private function findOrCreateDefinition(CookieConsentConfig $config, string $name): CookieDefinition
    {
        foreach ($this->definitionRepository->findByConfigOrdered($config) as $definition) {
            if ($definition->getName() === $name) {
                return $definition;
            }
        }

        return (new CookieDefinition())
            ->setConfig($config)
            ->setName($name);
    }
}
