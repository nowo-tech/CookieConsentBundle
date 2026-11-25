<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Fixtures\RemovedPorts\AsDecoratorRemoved;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieJar;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\ConsentRequestCookies;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A loadable application writer still decorating the id of the port removed in 2.0, optionally (W1-1 fixture: without
 * the check, Symfony would drop it silently).
 */
#[AsDecorator('Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface', onInvalid: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final class DecoratingCookieWriter implements ConsentCookieWriterInterface
{
    public function consentCookies(array $categories, string $key, array $granularCookies = []): ConsentCookieJar
    {
        return new ConsentCookieJar();
    }

    public function expiredConsentCookies(ConsentRequestCookies $requestCookies): ConsentCookieJar
    {
        return new ConsentCookieJar();
    }
}
