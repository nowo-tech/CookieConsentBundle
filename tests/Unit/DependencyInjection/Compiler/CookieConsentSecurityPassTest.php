<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentSecurityPass;
use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentAdminAccessSubscriber;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(CookieConsentSecurityPass::class)]
final class CookieConsentSecurityPassTest extends TestCase
{
    public function testNoopWhenWebUiParameterMissing(): void
    {
        $container = new ContainerBuilder();
        (new CookieConsentSecurityPass())->process($container);
        self::assertFalse($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
    }

    public function testNoopWhenWebUiDisabled(): void
    {
        $container = $this->baseContainer(enabled: false);
        (new CookieConsentSecurityPass())->process($container);
        self::assertFalse($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
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
        self::assertFalse($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
    }

    public function testRegistersSubscriberWhenSecurityPresent(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setAlias(CookieConsentAccessCheckerInterface::class, 'security.authorization_checker');

        (new CookieConsentSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
    }

    public function testNoopWhenAccessRolesEmpty(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false, accessRoles: []);
        $container->setDefinition('security.authorization_checker', new Definition());

        (new CookieConsentSecurityPass())->process($container);

        self::assertFalse($container->hasDefinition(CookieConsentAdminAccessSubscriber::class));
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
