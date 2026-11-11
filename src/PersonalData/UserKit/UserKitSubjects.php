<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData\UserKit;

use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubjectResolverInterface;

use function array_values;
use function is_string;
use function serialize;

/**
 * Asks the resolver for the user's subjects under every identifier the UserKit request knows (the original identifier,
 * plus `context['previous_email']` when it is a non-empty string) and drops duplicates (1.29).
 *
 * @internal
 */
final class UserKitSubjects
{
    /**
     * @param array<string, mixed> $context the UserKit request context
     * @param bool $ipWindows keep the subjects' IP windows (erasure with `personal_data.ip_windows: erase`); false
     *                        reduces every subject to its consent keys and drops the subjects without any
     *
     * @return list<CookieConsentPersonalDataSubject>
     */
    public static function resolve(CookieConsentPersonalDataSubjectResolverInterface $resolver, object $user, ?string $identifier, array $context, bool $ipWindows): array
    {
        $identifiers = [$identifier];
        $previous    = $context['previous_email'] ?? null;
        if (is_string($previous) && $previous !== '' && $previous !== $identifier) {
            $identifiers[] = $previous;
        }

        $subjects = [];
        foreach ($identifiers as $id) {
            foreach ($resolver->subjectsFor($user, $id) as $subject) {
                if (!$ipWindows) {
                    $subject = $subject->withoutIpWindows();
                    if (!$subject instanceof CookieConsentPersonalDataSubject) {
                        continue;
                    }
                }

                $subjects[serialize($subject->toArray())] = $subject;
            }
        }

        return array_values($subjects);
    }
}
