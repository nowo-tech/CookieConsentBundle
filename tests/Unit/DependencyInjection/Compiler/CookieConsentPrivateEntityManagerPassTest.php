<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentPrivateEntityManagerPass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManagerMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * 2.0: the private manager is the only mode on the application's default manager; a dedicated `doctrine.entity_manager`
 * keeps that manager as is unless `private_entity_manager: true` (1.34: the private manager on it). The private
 * manager's DBAL middleware is tagged for its source manager's connection only.
 */
#[CoversClass(CookieConsentPrivateEntityManagerPass::class)]
final class CookieConsentPrivateEntityManagerPassTest extends TestCase
{
    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        $this->deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored in tearDown)
        set_error_handler(function (int $level, string $message): bool {
            $this->deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testDoesNothingWithoutDoctrineBundle(): void
    {
        $container = $this->container(null, null);
        $container->removeDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE);

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertSame('', $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER), 'left untouched');
        self::assertSame([], $this->deprecations);
    }

    /**
     * @return iterable<string, array{string|null, bool|null, string, string, bool}>
     */
    public static function privateModes(): iterable
    {
        yield 'unset (2.0 default)' => [null, null, 'main', 'host', false];
        yield 'empty entity_manager' => ['', null, 'main', 'host', false];
        yield 'entity_manager naming the default manager' => ['main', null, 'main', 'host', false];
        yield 'true on the default manager (deprecated no-op)' => [null, true, 'main', 'host', true];
        yield 'true with entity_manager naming the default manager' => ['main', true, 'main', 'host', true];
        yield 'true with a dedicated manager (1.34 meaning kept)' => ['cookie_consent', true, 'cookie_consent', 'consent', false];
    }

    #[DataProvider('privateModes')]
    public function testThePrivateManagerIsCreatedOnItsSource(?string $named, ?bool $setting, string $source, string $connection, bool $deprecated): void
    {
        $container = $this->container($named, $setting);
        $container->setParameter('doctrine.default_entity_manager', 'main');

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertSame($source, $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        self::assertSame([['connection' => $connection]], $container->getDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE)->getTag('doctrine.middleware'));
        self::assertTrue($container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        self::assertSame($deprecated ? ['Since nowo-tech/cookie-consent-bundle 2.0: ' . NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_TRUE_DEPRECATION] : [], $this->deprecations);
    }

    public function testWithoutAnyDefaultManagerParameterTheManagerIsDefault(): void
    {
        $container = $this->container(null, null);
        $container->setParameter('doctrine.entity_managers', ['default' => 'doctrine.orm.default_entity_manager']);
        $container->setDefinition('doctrine.orm.default_entity_manager', new Definition('stdClass', [new Reference('doctrine.dbal.default_connection')]));

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertSame('default', $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        self::assertSame([['connection' => 'default']], $container->getDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE)->getTag('doctrine.middleware'));
    }

    public function testMissingParametersMeanUnsetOnTheDefaultManager(): void
    {
        $container = $this->container(null, null);
        $container->getParameterBag()->remove(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER);
        $container->getParameterBag()->remove(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER);
        $container->setParameter('doctrine.default_entity_manager', 'main');

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertSame('main', $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        self::assertSame([], $this->deprecations);
    }

    public function testADedicatedManagerWithoutTheOptionKeepsThatManager(): void
    {
        $container = $this->container('cookie_consent', null);
        $container->setParameter('doctrine.default_entity_manager', 'main');

        (new CookieConsentPrivateEntityManagerPass())->process($container);

        self::assertFalse($container->hasDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE));
        self::assertFalse($container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        self::assertSame('', $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER), 'left as load() set it');
        self::assertSame([], $this->deprecations);
    }

    public function testAnUnknownManagerFailsTheBuild(): void
    {
        $container = $this->container('missing', true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_cookie_consent.doctrine.entity_manager is "missing"');

        (new CookieConsentPrivateEntityManagerPass())->process($container);
    }

    private function container(?string $named, ?bool $setting): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER, $named);
        $container->setParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER, $setting);
        $container->setParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_PARAMETER, true);
        $container->setParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER, '');
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
