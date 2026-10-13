<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentSecurityPass;
use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentAdminAccessSubscriber;
use Nowo\CookieConsentBundle\Security\AllowAllCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function dirname;

#[CoversClass(CookieConsentSecurityPass::class)]
final class CookieConsentSecurityPassTest extends TestCase
{
    public function testNoopWhenWebUiParameterMissing(): void
    {
        $container = new ContainerBuilder();
        (new CookieConsentSecurityPass())->process($container);
        self::assertFalse($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
    }

    public function testRegistersNotFoundSubscriberWhenWebUiDisabled(): void
    {
        // Regression: web_ui.enabled false used to skip the subscriber while routing.yaml still loaded admin controllers.
        $container = $this->baseContainer(enabled: false, allowUnauthenticated: false);
        (new CookieConsentSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
        $definition = $container->getDefinition(CookieConsentAdminAccessSubscriber::class);
        self::assertFalse($definition->getArgument('$webUiEnabled'));
        self::assertTrue($definition->hasTag('kernel.event_subscriber'));
    }

    public function testFailsWithoutSecurityWhenNotAllowUnauthenticated(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);

        $this->expectException(InvalidConfigurationException::class);
        (new CookieConsentSecurityPass())->process($container);
    }

    public function testAllowsMissingSecurityWhenAllowUnauthenticated(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: true);
        (new CookieConsentSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
        $definition = $container->getDefinition(CookieConsentAdminAccessSubscriber::class);
        self::assertTrue($definition->getArgument('$webUiEnabled'));
        $checker = $definition->getArgument('$accessChecker');
        self::assertInstanceOf(Definition::class, $checker);
        self::assertSame(AllowAllCookieConsentAccessChecker::class, $checker->getClass());
    }

    public function testRegistersSubscriberWhenSecurityPresent(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setAlias(CookieConsentAccessCheckerInterface::class, 'security.authorization_checker');

        (new CookieConsentSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
        $definition = $container->getDefinition(CookieConsentAdminAccessSubscriber::class);
        self::assertTrue($definition->getArgument('$webUiEnabled'));
        self::assertInstanceOf(Reference::class, $definition->getArgument('$accessChecker'));
    }

    public function testRegistersSubscriberWhenAccessRolesEmpty(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false, accessRoles: []);
        $container->setDefinition('security.authorization_checker', new Definition());

        (new CookieConsentSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
    }

    public function testDoesNotDuplicateExistingSubscriber(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setDefinition(CookieConsentAdminAccessSubscriber::class, new Definition());

        (new CookieConsentSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
    }

    /**
     * Regression: the services.yaml exclusion leaves an abstract `container.excluded` placeholder for the
     * subscriber class, which used to short-circuit the registration in real applications.
     */
    public function testReplacesExcludedPlaceholderLeftByServicesYaml(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);
        $container->setDefinition('security.authorization_checker', new Definition());
        foreach (['categories', 'form_action', 'csrf_protection', 'disabled_routes', 'skip_render_routes', 'render_routes', 'use_database_config', 'fetch_config_via_api', 'ui_theme'] as $parameter) {
            $container->setParameter(Configuration::ALIAS . '.' . $parameter, null);
        }
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 4) . '/src/Resources/config')))->load('services.yaml');
        self::assertTrue($container->getDefinition(CookieConsentAdminAccessSubscriber::class)->hasTag('container.excluded'));

        (new CookieConsentSecurityPass())->process($container);

        $definition = $container->getDefinition(CookieConsentAdminAccessSubscriber::class);
        self::assertFalse($definition->hasTag('container.excluded'));
        self::assertFalse($definition->isAbstract());
        self::assertTrue($definition->hasTag('kernel.event_subscriber'));
        self::assertInstanceOf(Reference::class, $definition->getArgument('$accessChecker'));
    }

    public function testKeepsHostDefinedSubscriber(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);
        $container->setDefinition('security.authorization_checker', new Definition());
        $hostDefinition = (new Definition(CookieConsentAdminAccessSubscriber::class))->addTag('kernel.event_subscriber');
        $container->setDefinition(CookieConsentAdminAccessSubscriber::class, $hostDefinition);

        (new CookieConsentSecurityPass())->process($container);

        self::assertSame($hostDefinition, $container->getDefinition(CookieConsentAdminAccessSubscriber::class));
        self::assertSame([], $hostDefinition->getArguments());
    }

    /**
     * @param list<string> $accessRoles
     */
    private function baseContainer(
        bool $enabled = true,
        bool $allowUnauthenticated = true,
        array $accessRoles = ['ROLE_ADMIN'],
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS . '.web_ui.enabled', $enabled);
        $container->setParameter(Configuration::ALIAS . '.security.allow_unauthenticated', $allowUnauthenticated);
        $container->setParameter(Configuration::ALIAS . '.security.access_roles', $accessRoles);

        return $container;
    }
}
