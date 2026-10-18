<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Removes the {@see CookieConsentTestKernel} cache root of this process when the run starts (stale directory of a
 * previous process with the same PID) and when it ends; never between tests, because a container class already
 * loaded by PHP keeps reading lazy proxy files from the directory it was dumped to.
 */
final class KernelCacheExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscribers(
            new class implements ExecutionStartedSubscriber {
                public function notify(ExecutionStarted $event): void
                {
                    (new Filesystem())->remove(CookieConsentTestKernel::processCacheRoot());
                }
            },
            new class implements ExecutionFinishedSubscriber {
                public function notify(ExecutionFinished $event): void
                {
                    (new Filesystem())->remove(CookieConsentTestKernel::processCacheRoot());
                }
            },
        );
    }
}
