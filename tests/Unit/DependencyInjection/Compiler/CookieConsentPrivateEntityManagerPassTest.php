<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentPrivateEntityManagerPass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManagerMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 1.34: the private manager's source is `doctrine.entity_manager`, else the application's default manager, and its
 * DBAL middleware is tagged for that manager's connection only.
 */
#[CoversClass(CookieConsentPrivateEntityManagerPass::class)]
final class CookieConsentPrivateEntityManagerPassTest extends TestCase
{
    public function testDoesNothingInTheDefaultMode(): void
    {
        $container = $this->container(null);
        $container->removeDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE);
        $container->getParameterBag()->remove(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER);

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertFalse($container->hasParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
    }

    public function testDefaultsToTheApplicationsDefaultManager(): void
    {
        $container = $this->container('');
        $container->setParameter('doctrine.default_entity_manager', 'main');

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertSame('main', $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        self::assertSame([['connection' => 'host']], $container->getDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE)->getTag('doctrine.middleware'));
    }

    public function testWithoutAnyDefaultManagerParameterTheManagerIsDefault(): void
    {
        $container = $this->container(null);
        $container->getParameterBag()->remove(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER);
        $container->setParameter('doctrine.entity_managers', ['default' => 'doctrine.orm.default_entity_manager']);

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertSame('default', $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        self::assertSame([['connection' => 'default']], $container->getDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE)->getTag('doctrine.middleware'));
    }

    public function testUsesTheConfiguredEntityManagerAndItsConnection(): void
    {
        $container = $this->container('cookie_consent');

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertSame('cookie_consent', $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        self::assertSame([['connection' => 'consent']], $container->getDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE)->getTag('doctrine.middleware'));
    }

    public function testAnUnknownManagerFailsTheBuild(): void
    {
        $container = $this->container('missing');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_cookie_consent.doctrine.entity_manager is "missing"');

        (new CookieConsentPrivateEntityManagerPass())->process($container);
    }

    private function container(?string $source): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER, $source);
        $container->setParameter('doctrine.entity_managers', [
            'main'           => 'doctrine.orm.main_entity_manager',
            'cookie_consent' => 'doctrine.orm.cookie_consent_entity_manager',
        ]);
        $container->setDefinition('doctrine.orm.main_entity_manager', new Definition('stdClass', [new Reference('doctrine.dbal.host_connection')]));
        $container->setDefinition('doctrine.orm.cookie_consent_entity_manager', new Definition('stdClass', [new Reference('doctrine.dbal.consent_connection')]));
        $container->setDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE, new Definition(PrivateEntityManagerMiddleware::class));

        return $container;
    }
}
