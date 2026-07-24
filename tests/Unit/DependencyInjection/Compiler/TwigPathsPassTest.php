<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

final class TwigPathsPassTest extends TestCase
{
    public function testAddsBundleViewsPathToNativeLoader(): void
    {
        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native_filesystem', $loader);

        (new TwigPathsPass())->process($container);

        $calls = $loader->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addPath', $calls[0][0]);
        self::assertSame('NowoCookieConsentBundle', $calls[0][1][1]);
        self::assertStringEndsWith('/Resources/views', $calls[0][1][0]);
    }

    public function testNoOpWhenTwigLoaderMissing(): void
    {
        $container = new ContainerBuilder();

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native_filesystem'));
    }

    public function testUsesNativeLoaderDefinitionWhenPresent(): void
    {
        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native', $loader);

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($loader->getMethodCalls());
    }

    public function testUsesNativeLoaderAliasWhenPresent(): void
    {
        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setDefinition('custom.native.loader', $loader);
        $container->setAlias('twig.loader.native', 'custom.native.loader');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($loader->getMethodCalls());
    }
}
