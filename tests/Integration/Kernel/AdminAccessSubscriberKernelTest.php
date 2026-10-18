<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentAdminAccessSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function is_array;

/**
 * Regression (1.15.1): CookieConsentSecurityPass registered the subscriber without a class, so a real
 * application failed to compile with "Invalid service ...CookieConsentAdminAccessSubscriber: the class is not set".
 */
final class AdminAccessSubscriberKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @return iterable<string, array{bool, array<string, mixed>}>
     */
    public static function applications(): iterable
    {
        yield 'admin enabled with SecurityBundle' => [true, [
            'web_ui'   => ['enabled' => true],
            'security' => ['access_roles' => ['ROLE_ADMIN']],
        ]];
        yield 'admin enabled without SecurityBundle (allow_unauthenticated)' => [false, [
            'web_ui'   => ['enabled' => true],
            'security' => ['allow_unauthenticated' => true],
        ]];
        yield 'admin disabled without SecurityBundle' => [false, [
            'web_ui' => ['enabled' => false],
        ]];
    }

    /**
     * @param array<string, mixed> $bundleConfig
     */
    #[DataProvider('applications')]
    public function testRealKernelCompilesAndRegistersTheAdminAccessSubscriber(bool $withSecurity, array $bundleConfig): void
    {
        $this->kernel = new CookieConsentTestKernel($withSecurity, $bundleConfig);
        $this->kernel->boot();

        $dispatcher = $this->kernel->getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $found = false;
        foreach ($dispatcher->getListeners(KernelEvents::CONTROLLER) as $listener) {
            if (is_array($listener) && $listener[0] instanceof CookieConsentAdminAccessSubscriber && $listener[1] === 'onKernelController') {
                $found = true;
            }
        }

        self::assertTrue($found, 'CookieConsentAdminAccessSubscriber must listen to kernel.controller.');
    }
}
