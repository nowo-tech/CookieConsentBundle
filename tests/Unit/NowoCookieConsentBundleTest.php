<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\NowoCookieConsentBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NowoCookieConsentBundleTest extends TestCase
{
    public function testBundleName(): void
    {
        self::assertSame('NowoCookieConsentBundle', (new NowoCookieConsentBundle())->getName());
    }

    public function testBuildRegistersCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentBundle())->build($container);

        $passTypes = array_map(
            static fn (object $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getPasses(),
        );

        self::assertContains(TwigPathsPass::class, $passTypes);
    }

    public function testGetContainerExtensionReturnsNowoExtension(): void
    {
        $bundle    = new NowoCookieConsentBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(NowoCookieConsentExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }
}
