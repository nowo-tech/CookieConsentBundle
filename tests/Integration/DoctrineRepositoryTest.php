<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use PHPUnit\Framework\TestCase;

use function dirname;

final class DoctrineRepositoryTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2) . '/src/Entity'],
            isDevMode: true,
        );

        $connection          = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->entityManager = new EntityManager($connection, $config);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testConfigRepositoryFinders(): void
    {
        $default = (new CookieConsentConfig())
            ->setEnabled(true)
            ->setDefault(true)
            ->setName('Default')
            ->setPriority(1);

        $custom = (new CookieConsentConfig())
            ->setEnabled(true)
            ->setDefault(false)
            ->setName('Custom')
            ->setPriority(5);

        $disabled = (new CookieConsentConfig())
            ->setEnabled(false)
            ->setDefault(false);

        $this->entityManager->persist($default);
        $this->entityManager->persist($custom);
        $this->entityManager->persist($disabled);
        $this->entityManager->flush();

        $repository = new CookieConsentConfigRepository($this->createRegistry());

        self::assertSame($default->getId(), $repository->findDefaultEnabled()?->getId());
        self::assertCount(2, $repository->findAllEnabled());
        self::assertSame('Custom', $repository->findAllEnabledNonDefault()[0]->getName());
    }

    public function testTranslationRepositoryFindsLocale(): void
    {
        $config      = (new CookieConsentConfig())->setEnabled(true)->setDefault(true);
        $translation = (new CookieConsentConfigTranslation())
            ->setLocale('es')
            ->setConsentModalTitle('Hola')
            ->setConsentModalDescription('Intro')
            ->setConsentModalAcceptAllBtn('Todo')
            ->setConsentModalAcceptNecessaryBtn('Necesarias')
            ->setConfig($config);

        $this->entityManager->persist($config);
        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        $repository = new CookieConsentConfigTranslationRepository($this->createRegistry());
        $found      = $repository->findOneForConfigAndLocale($config, 'es');

        self::assertNotNull($found);
        self::assertSame('Hola', $found->getConsentModalTitle());
    }

    private function createRegistry(): ManagerRegistry
    {
        $entityManager = $this->entityManager;
        $registry      = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return $registry;
    }
}
