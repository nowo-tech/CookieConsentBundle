<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;

/**
 * Host env vars of the test kernel, read at runtime by %env(…)% (no process env mutation in tests).
 */
final class TestEnvVarLoader implements EnvVarLoaderInterface
{
    public const LOCALES_JSON = 'NOWO_COOKIE_CONSENT_TEST_LOCALES_JSON';

    public const LOCALES_CSV = 'NOWO_COOKIE_CONSENT_TEST_LOCALES_CSV';

    public function loadEnvVars(): array
    {
        return [self::LOCALES_JSON => '["pt","en"]', self::LOCALES_CSV => 'it,en'];
    }
}
