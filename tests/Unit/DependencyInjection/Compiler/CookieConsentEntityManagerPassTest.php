<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentEntityManagerPass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class CookieConsentEntityManagerPassTest extends TestCase
{
    public function testDoesNothingWithoutTheParameterOrWithTheDefaultManager(): void
    {
        $container = $this->container(null);
        (new CookieConsentEntityManagerPass())->process($container);
        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());

        $container = $this->container(null);
        $container->getParameterBag()->remove(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER);
        (new CookieConsentEntityManagerPass())->process($container);
        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
    }

    public function testRemovesTheBundleMappingFromTheOtherManagers(): void
    {
        $container = $this->container('cookie_consent');
        (new CookieConsentEntityManagerPass())->process($container);

        $defaultCalls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame(['App\\Entity'], array_map(static fn (array $call): mixed => $call[1][1], $defaultCalls));
        self::assertCount(1, $container->getDefinition('doctrine.orm.cookie_consent_metadata_driver')->getMethodCalls());
    }

    public function testFailsWhenTheManagerIsUnknown(): void
    {
        $container = $this->container('missing');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"missing", but no such Doctrine entity manager');
        (new CookieConsentEntityManagerPass())->process($container);
    }

    public function testFailsWhenNoManagerIsConfiguredAtAll(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER, 'cookie_consent');

        $this->expectException(InvalidConfigurationException::class);
        (new CookieConsentEntityManagerPass())->process($container);
    }

    public function testFailsWhenTheConfiguredManagerDoesNotMapTheEntities(): void
    {
        $container = $this->container('cookie_consent');
        $container->getDefinition('doctrine.orm.cookie_consent_metadata_driver')->setMethodCalls([]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not map');
        (new CookieConsentEntityManagerPass())->process($container);
    }

    private function container(?string $entityManager): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER, $entityManager);
        $container->setParameter('doctrine.entity_managers', [
            'default'        => 'doctrine.orm.default_entity_manager',
            'cookie_consent' => 'doctrine.orm.cookie_consent_entity_manager',
            'no_chain'       => 'doctrine.orm.no_chain_entity_manager',
        ]);

        $container->setDefinition('doctrine.orm.default_metadata_driver', (new Definition())
            ->addMethodCall('addDriver', [new Reference('attribute'), 'App\\Entity'])
            ->addMethodCall('addDriver', [new Reference('attribute'), CookieConsentEntityManagerPass::ENTITY_NAMESPACE]));
        $container->setDefinition('doctrine.orm.cookie_consent_metadata_driver', (new Definition())
            ->addMethodCall('addDriver', [new Reference('bundle'), CookieConsentEntityManagerPass::ENTITY_NAMESPACE]));

        return $container;
    }
}
