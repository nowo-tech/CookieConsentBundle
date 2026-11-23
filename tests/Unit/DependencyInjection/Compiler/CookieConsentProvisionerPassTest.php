<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentProvisionerPass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * 1.36: the provisioning manager's source is `doctrine.entity_manager`, else the application's default manager.
 */
#[CoversClass(CookieConsentProvisionerPass::class)]
final class CookieConsentProvisionerPassTest extends TestCase
{
    public function testDoesNothingWithoutTheProvisioningPort(): void
    {
        $container = new ContainerBuilder();

        (new CookieConsentProvisionerPass())->process($container);

        self::assertFalse($container->hasParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER));
    }

    public function testKeepsTheConfiguredEntityManager(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER, 'cookie_consent');
        $container->setParameter('doctrine.default_entity_manager', 'main');

        (new CookieConsentProvisionerPass())->process($container);

        self::assertSame('cookie_consent', $container->getParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER));
    }

    public function testDefaultsToTheApplicationsDefaultManager(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER, '');
        $container->setParameter('doctrine.default_entity_manager', 'main');

        (new CookieConsentProvisionerPass())->process($container);

        self::assertSame('main', $container->getParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER));
    }

    public function testWithoutADefaultManagerParameterTheManagerIsDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER, '');

        (new CookieConsentProvisionerPass())->process($container);

        self::assertSame('default', $container->getParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER));
    }
}
