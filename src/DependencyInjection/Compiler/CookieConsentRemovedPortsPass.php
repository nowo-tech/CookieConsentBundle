<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function preg_match;
use function preg_quote;
use function sprintf;

/**
 * 2.0: refuses the host services that still rely on a type removed in 2.0, instead of letting them vanish.
 *
 * - A service or alias with the id of a removed port (`CookieHandlerInterface`: a host alias or decorator target).
 * - Any definition whose loader errors name a removed type by its exact class name: an autodiscovered class of the
 *   application that still implements `CookieHandlerInterface` or extends `CookieConsentConfigSettingsType`, or carries
 *   `#[AsDecorator]` / `#[AsAlias]` for the removed port, cannot be loaded; Symfony keeps it as a private, unused
 *   definition with an error and drops it silently, so the host's cookie writer would be ignored without notice.
 *
 * - A definition decorating a removed port (`#[AsDecorator]`, also with `onInvalid: IGNORE`, which Symfony would drop
 *   silently).
 *
 * Registered twice before optimization: at priority 1000, while the loader errors still exist and before any pass
 * removes definitions, and at priority 99, after Symfony's `AutowireAsDecoratorPass` (100) has read `#[AsDecorator]`.
 * An unrelated missing class keeps its usual lazy error.
 */
final class CookieConsentRemovedPortsPass implements CompilerPassInterface
{
    /** Removed type => [deprecated since, replacement]. */
    public const REMOVED_TYPES = [
        'Nowo\\CookieConsentBundle\\Cookie\\CookieHandlerInterface' => [
            '1.30',
            'implement ' . ConsentCookieWriterInterface::class . ' (consentCookies() / expiredConsentCookies()) and alias that interface to your service, or decorate "nowo_cookie_consent.cookie_writer"',
        ],
        'Nowo\\CookieConsentBundle\\Form\\CookieConsentConfigSettingsType' => [
            '1.5.0',
            'use Nowo\\CookieConsentBundle\\Form\\Settings\\CookieConsentConfigFullSettingsType (same fields)',
        ],
    ];

    /**
     * Fails the build on a service that still uses a removed type.
     */
    public function process(ContainerBuilder $container): void
    {
        foreach (self::REMOVED_TYPES as $type => [$since, $replacement]) {
            if ($container->hasDefinition($type) || $container->hasAlias($type)) {
                throw new LogicException(sprintf('The service "%s" uses "%s", deprecated since nowo-tech/cookie-consent-bundle %s and removed in 2.0: %s. See UPGRADING.md "To 2.0.0".', $type, $type, $since, $replacement));
            }
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            $decorated = $definition->getDecoratedService()[0] ?? null;
            if ($decorated !== null && isset(self::REMOVED_TYPES[$decorated])) {
                [$since, $replacement] = self::REMOVED_TYPES[$decorated];

                throw new LogicException(sprintf('The service "%s" decorates "%s", deprecated since nowo-tech/cookie-consent-bundle %s and removed in 2.0: %s. See UPGRADING.md "To 2.0.0".', $id, $decorated, $since, $replacement));
            }

            foreach ($definition->getErrors() as $error) {
                foreach (self::REMOVED_TYPES as $type => [$since, $replacement]) {
                    if (preg_match('/(?<![\w\\\\])' . preg_quote($type, '/') . '(?![\w\\\\])/', (string) $error) === 1) {
                        throw new LogicException(sprintf('The service "%s" uses "%s", deprecated since nowo-tech/cookie-consent-bundle %s and removed in 2.0: %s. See UPGRADING.md "To 2.0.0". Loader error: %s', $id, $type, $since, $replacement, $error));
                    }
                }
            }
        }
    }
}
