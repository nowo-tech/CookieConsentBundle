<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentSchemaReadySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * `doctrine.entity_manager` through a real kernel with two entity managers on two SQLite databases: the bundle
 * entities are mapped on the dedicated manager only, consents and the schema probe use its connection, and the
 * logger flushes no manager at all.
 */
final class DedicatedEntityManagerKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testEntitiesAndConsentLogLiveOnTheDedicatedManager(): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_logger' => true,
            'categories' => ['analytics'],
            'doctrine'   => ['entity_manager' => 'cookie_consent'],
            'security'   => ['allow_unauthenticated' => true],
        ], doctrineConfig: [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default' => ['url' => 'sqlite:///:memory:'],
                    'consent' => ['url' => 'sqlite:///:memory:'],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers'        => [
                    'default'        => ['connection' => 'default'],
                    'cookie_consent' => ['connection' => 'consent'],
                ],
            ],
        ]);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $consentManager = $registry->getManager('cookie_consent');
        $hostManager    = $registry->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $consentManager);
        self::assertInstanceOf(EntityManagerInterface::class, $hostManager);

        self::assertSame($consentManager, $registry->getManagerForClass(CookieConsentLog::class));
        self::assertTrue($hostManager->getMetadataFactory()->isTransient(CookieConsentLog::class), 'not mapped on the host manager');

        (new SchemaTool($consentManager))->createSchema($consentManager->getMetadataFactory()->getAllMetadata());

        $subscriber = $container->get(CookieConsentSchemaReadySubscriber::class);
        self::assertInstanceOf(CookieConsentSchemaReadySubscriber::class, $subscriber);
        $connection = (fn (): object => $this->connection)->call($subscriber);
        self::assertSame($consentManager->getConnection(), $connection, 'schema probe runs on the dedicated connection');

        $consentManager->persist((new CookieConsentConfig())->setName('Pending in the bundle manager'));

        $stack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $logger = $container->get(CookieLoggerInterface::class);
        self::assertInstanceOf(CookieLoggerInterface::class, $logger);
        $logger->log(['analytics' => true], 'kernel-key');

        self::assertSame(
            ['kernel-key'],
            $consentManager->getConnection()->fetchFirstColumn('SELECT cookie_consent_key FROM dashboard_cookie_log'),
        );
        self::assertSame(
            [],
            $consentManager->getConnection()->fetchFirstColumn('SELECT name FROM dashboard_cookie_config'),
            'the logger does not flush its own manager either',
        );
    }
}
