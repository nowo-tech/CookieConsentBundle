<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes the listed services / aliases public so a kernel test can fetch them even when nothing uses them.
 */
final class PublicForTestsPass implements CompilerPassInterface
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private readonly array $ids,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($this->ids as $id) {
            if ($container->hasAlias($id)) {
                $container->getAlias($id)->setPublic(true);
            } elseif ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            }
        }
    }
}
