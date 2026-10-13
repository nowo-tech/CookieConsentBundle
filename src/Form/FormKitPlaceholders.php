<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Closure;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormTypeInterface;

use function is_a;
use function is_array;
use function is_string;

/**
 * Placeholder handling for the bundle's FormKit-based form types.
 *
 * FormKit's convention injects an `attr.placeholder` translation key ("{form}.{field}.placeholder") on every
 * field. The bundle ships no such keys, so they rendered raw (also on checkboxes and selects, where a
 * placeholder is meaningless), and an explicit `placeholder` option lost against that convention key. Here:
 *
 *  - fields only get a placeholder when the form type sets one explicitly;
 *  - `attr.placeholder` set by the form type is kept as is;
 *  - a root `placeholder` on a {@see ChoiceType} stays the ChoiceType empty-option label (FormKit would
 *    move it to `attr.placeholder`); on other types it becomes `attr.placeholder`;
 *  - checkboxes never get one.
 *
 * @internal
 */
final class FormKitPlaceholders
{
    /**
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, mixed> $options
     * @param Closure(array<string, mixed>): array<string, mixed> $resolve FormKit option resolution
     *
     * @return array<string, mixed>
     */
    public static function resolve(string $type, array $options, Closure $resolve): array
    {
        $attrPlaceholder = is_array($options['attr'] ?? null) ? ($options['attr']['placeholder'] ?? null) : null;
        $rootPlaceholder = $options['placeholder'] ?? null;

        $options['placeholder'] = false;
        $resolved               = $resolve($options);

        if (is_a($type, CheckboxType::class, true)) {
            return $resolved;
        }

        if (is_string($attrPlaceholder)) {
            $resolved['attr']                = is_array($resolved['attr'] ?? null) ? $resolved['attr'] : [];
            $resolved['attr']['placeholder'] = $attrPlaceholder;
        }

        if ($rootPlaceholder === null || $rootPlaceholder === false) {
            return $resolved;
        }

        if (is_a($type, ChoiceType::class, true)) {
            $resolved['placeholder'] = $rootPlaceholder;
        } elseif (is_string($rootPlaceholder) && $attrPlaceholder === null) {
            $resolved['attr']                = is_array($resolved['attr'] ?? null) ? $resolved['attr'] : [];
            $resolved['attr']['placeholder'] = $rootPlaceholder;
        }

        return $resolved;
    }
}
