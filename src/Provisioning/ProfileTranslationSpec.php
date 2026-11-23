<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function preg_match;
use function sprintf;

/**
 * The copy of the default profile's modals in one locale (1.36), for {@see DefaultProfileSpec::withTranslation()}.
 *
 * The four required texts are always written. An optional text is written only when it is given: null (or a key
 * missing from {@see fromArray()}) keeps the stored value, so a text an admin edited is never cleared by a seeder that
 * does not manage it; an empty string clears it. The grouped preference sections of the translation are not managed
 * and keep their stored value. Natural key: the locale, compared byte for byte (`===`, REQ-SEC-006).
 *
 * Every text is checked against its column length when the object is built; the privacy route must be a route name
 * (the modal renders it with `path()`).
 */
final readonly class ProfileTranslationSpec
{
    /**
     * Managed texts and their column length (null: TEXT column).
     *
     * @var array<string, int|null>
     */
    public const FIELDS = [
        'consentModalTitle'                  => 100,
        'consentModalDescription'            => null,
        'consentModalAcceptAllBtn'           => 30,
        'consentModalAcceptNecessaryBtn'     => 30,
        'consentModalLabel'                  => 100,
        'consentModalShowPreferencesBtn'     => 30,
        'consentModalFooter'                 => null,
        'preferencesModalTitle'              => 100,
        'preferencesModalAcceptAllBtn'       => 30,
        'preferencesModalAcceptNecessaryBtn' => 30,
        'preferencesModalSavePreferencesBtn' => 30,
        'preferencesModalCloseIconLabel'     => 30,
        'privacyRoute'                       => 255,
    ];

    /** A route name: the modal renders the privacy link with `path()`, so a URL or a path would fail every page. */
    public const PRIVACY_ROUTE_PATTERN = '/^[A-Za-z0-9_.\-]+$/D';

    /** Texts the entity stores as NOT NULL (the others are optional). */
    public const REQUIRED_FIELDS = ['consentModalTitle', 'consentModalDescription', 'consentModalAcceptAllBtn', 'consentModalAcceptNecessaryBtn'];

    /**
     * @param string $locale The locale of the copy ("en", "es", "pt_BR"), its natural key
     * @param string $consentModalTitle Title of the consent modal (max 100)
     * @param string $consentModalDescription Text of the consent modal
     * @param string $consentModalAcceptAllBtn "Accept all" button (max 30)
     * @param string $consentModalAcceptNecessaryBtn "Only necessary" button (max 30)
     * @param string|null $consentModalLabel Accessible label of the consent modal (max 100). Every optional text: null keeps the stored value, "" clears it
     * @param string|null $consentModalShowPreferencesBtn "Preferences" button (max 30)
     * @param string|null $consentModalFooter Footer of the consent modal
     * @param string|null $preferencesModalTitle Title of the preferences modal (max 100)
     * @param string|null $preferencesModalAcceptAllBtn "Accept all" button of the preferences modal (max 30)
     * @param string|null $preferencesModalAcceptNecessaryBtn "Only necessary" button of the preferences modal (max 30)
     * @param string|null $preferencesModalSavePreferencesBtn "Save" button of the preferences modal (max 30)
     * @param string|null $preferencesModalCloseIconLabel Label of the close icon (max 30)
     * @param string|null $privacyRoute Symfony route name of the privacy policy, without required parameters (max 255; letters, digits, `_`, `.`, `-`; never a URL or a path)
     *
     * @throws InvalidProvisioningSpecException When the locale is malformed or a text is longer than its column
     */
    public function __construct(
        public string $locale,
        public string $consentModalTitle,
        public string $consentModalDescription,
        public string $consentModalAcceptAllBtn,
        public string $consentModalAcceptNecessaryBtn,
        public ?string $consentModalLabel = null,
        public ?string $consentModalShowPreferencesBtn = null,
        public ?string $consentModalFooter = null,
        public ?string $preferencesModalTitle = null,
        public ?string $preferencesModalAcceptAllBtn = null,
        public ?string $preferencesModalAcceptNecessaryBtn = null,
        public ?string $preferencesModalSavePreferencesBtn = null,
        public ?string $preferencesModalCloseIconLabel = null,
        public ?string $privacyRoute = null,
    ) {
        SpecAssert::locale('Profile translation locale', $locale);
        foreach ($this->texts() as $field => $value) {
            SpecAssert::length(sprintf('Profile translation "%s" %s', $locale, $field), (string) $value, self::FIELDS[$field]);
        }

        if ($privacyRoute !== null && $privacyRoute !== '' && preg_match(self::PRIVACY_ROUTE_PATTERN, $privacyRoute) !== 1) {
            throw new InvalidProvisioningSpecException(sprintf('Profile translation "%s" privacyRoute must be a Symfony route name (letters, digits, "_", "." and "-"; not a URL or a path), got "%s".', $locale, $privacyRoute));
        }
    }

    /**
     * Builds the copy from a decoded fixture (JSON / YAML): the keys are the constructor's parameter names except the
     * locale, which is given separately (the fixtures key their copies by locale). The four required texts must be
     * given; an optional key that is missing (or null) keeps the stored value. Unknown keys and values of the wrong
     * type are refused.
     *
     * @param array<mixed> $texts
     *
     * @throws InvalidProvisioningSpecException
     */
    public static function fromArray(string $locale, array $texts): self
    {
        $what = sprintf('the profile translation "%s"', $locale);
        $data = SpecAssert::map($what, $texts);
        SpecAssert::knownKeys($what, $data, array_keys(self::FIELDS));
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidProvisioningSpecException(sprintf('Missing key "%s" in %s.', $field, $what));
            }
        }

        $text = static fn (string $field): ?string => SpecAssert::nullableString(sprintf('Profile translation "%s" %s', $locale, $field), $data[$field] ?? null);

        return new self(
            $locale,
            SpecAssert::string(sprintf('Profile translation "%s" consentModalTitle', $locale), $data['consentModalTitle']),
            SpecAssert::string(sprintf('Profile translation "%s" consentModalDescription', $locale), $data['consentModalDescription']),
            SpecAssert::string(sprintf('Profile translation "%s" consentModalAcceptAllBtn', $locale), $data['consentModalAcceptAllBtn']),
            SpecAssert::string(sprintf('Profile translation "%s" consentModalAcceptNecessaryBtn', $locale), $data['consentModalAcceptNecessaryBtn']),
            $text('consentModalLabel'),
            $text('consentModalShowPreferencesBtn'),
            $text('consentModalFooter'),
            $text('preferencesModalTitle'),
            $text('preferencesModalAcceptAllBtn'),
            $text('preferencesModalAcceptNecessaryBtn'),
            $text('preferencesModalSavePreferencesBtn'),
            $text('preferencesModalCloseIconLabel'),
            $text('privacyRoute'),
        );
    }

    /**
     * The texts provisioning writes, by field name (keys of {@see FIELDS}): the required ones and the optional ones that
     * were given (not null).
     *
     * @return array<string, string>
     */
    public function texts(): array
    {
        return array_filter([
            'consentModalTitle'                  => $this->consentModalTitle,
            'consentModalDescription'            => $this->consentModalDescription,
            'consentModalAcceptAllBtn'           => $this->consentModalAcceptAllBtn,
            'consentModalAcceptNecessaryBtn'     => $this->consentModalAcceptNecessaryBtn,
            'consentModalLabel'                  => $this->consentModalLabel,
            'consentModalShowPreferencesBtn'     => $this->consentModalShowPreferencesBtn,
            'consentModalFooter'                 => $this->consentModalFooter,
            'preferencesModalTitle'              => $this->preferencesModalTitle,
            'preferencesModalAcceptAllBtn'       => $this->preferencesModalAcceptAllBtn,
            'preferencesModalAcceptNecessaryBtn' => $this->preferencesModalAcceptNecessaryBtn,
            'preferencesModalSavePreferencesBtn' => $this->preferencesModalSavePreferencesBtn,
            'preferencesModalCloseIconLabel'     => $this->preferencesModalCloseIconLabel,
            'privacyRoute'                       => $this->privacyRoute,
        ], static fn (?string $text): bool => $text !== null);
    }
}
