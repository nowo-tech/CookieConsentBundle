<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support;

use Nowo\CookieConsentBundle\Admin\AdminPageFactory;
use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Form\CookieDefinitionTranslationType;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Nowo\CookieConsentBundle\Form\DeleteCookieDefinitionType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigAppearanceSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigBehaviorSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigConsentModalSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigPreferencesModalSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigProfileSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigRouteTargetingSettingsType;
use ReflectionProperty;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function http_build_query;
use function str_replace;

/**
 * Realistic admin page context (view models built by {@see AdminPageFactory} from entities and the real form
 * types) for the page contract test and the template tests.
 */
final class AdminPageFixtures
{
    /**
     * @param array<string, string> $templates
     */
    public static function factory(?string $layout = null, ?string $rowActionsDisplay = null, array $templates = []): AdminPageFactory
    {
        return new AdminPageFactory(self::urlGenerator(), $layout, $rowActionsDisplay, $templates);
    }

    /**
     * Context of `admin/cookie_definition/index.html.twig` (three cookies, page 1 of 2, or empty).
     *
     * @return array<string, mixed>
     */
    public static function definitionList(bool $empty = false, ?AdminPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $config      = self::config();
        $definitions = $empty ? [] : [
            self::definition(7, '_ga', 'analytics', '2 years', 'Google', 'Distinguishes visitors for Google Analytics.', false),
            self::definition(8, 'PHPSESSID', 'required', 'Session', 'Acme', 'Keeps the user session.', true),
            self::definition(9, 'lang', 'preferences', '1 year', '', '', false),
        ];
        $deleteForms = [];
        foreach ($definitions as $definition) {
            $deleteForms[(int) $definition->getId()] = self::deleteForm((int) $definition->getId());
        }

        return [
            'page'     => $factory->definitionList($config, $definitions, $deleteForms, 1, 3, $empty ? 0 : 5, 'en'),
            'area_nav' => $factory->areaNav($config, AdminPageFactory::AREA_COOKIES),
        ];
    }

    /**
     * Context of `admin/cookie_definition/form.html.twig` (edit form of a real CookieDefinitionType).
     *
     * @return array<string, mixed>
     */
    public static function definitionForm(bool $isNew = false, ?AdminPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $config     = self::config();
        $definition = $isNew ? new CookieDefinition() : self::definition(7, '_ga', 'analytics', '2 years', 'Google', 'Distinguishes visitors.', false);
        if ($isNew) {
            $definition->addTranslation((new CookieDefinitionTranslation())->setLocale('en'));
        }
        $form = self::forms()->create(CookieDefinitionType::class, $definition)->createView();

        return [
            'page'     => $factory->definitionForm($config, $definition, $form, $isNew),
            'area_nav' => $factory->areaNav($config, AdminPageFactory::AREA_COOKIES),
        ];
    }

    /**
     * Context of `admin/config/settings.html.twig` (one section with its real form type).
     *
     * @return array<string, mixed>
     */
    public static function settings(CookieConsentConfigSettingsSection $section = CookieConsentConfigSettingsSection::Appearance, ?AdminPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $config = self::config();
        $form   = self::forms()->create($section->formType(), $config)->createView();

        return [
            'page'     => $factory->settings($config, $section, $form),
            'area_nav' => $factory->areaNav($config, AdminPageFactory::AREA_SETTINGS),
        ];
    }

    public static function config(): CookieConsentConfig
    {
        $config = (new CookieConsentConfig())->setName('Public site');
        self::setId($config, 1);

        return $config;
    }

    public static function definition(int $id, string $name, string $category, string $duration, string $provider, string $purpose, bool $allowedByDefault): CookieDefinition
    {
        $definition = (new CookieDefinition())
            ->setName($name)
            ->setCategory($category)
            ->setDuration($duration)
            ->setAllowedByDefault($allowedByDefault)
            ->setConfig(self::config());
        if ($provider !== '' || $purpose !== '') {
            $definition->addTranslation((new CookieDefinitionTranslation())->setLocale('en')->setProvider($provider)->setPurpose($purpose));
        }
        self::setId($definition, $id);

        return $definition;
    }

    public static function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/' . str_replace('_', '-', $name) . ($parameters !== [] ? '?' . http_build_query($parameters) : '');
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }
        };
    }

    private static function deleteForm(int $id): FormView
    {
        return self::forms()->createNamed(DeleteCookieDefinitionType::FORM_NAME, DeleteCookieDefinitionType::class, null, [
            'action'        => '/cookie-consent-config/1/cookies/' . $id . '/delete',
            'csrf_token_id' => 'delete-cookie-definition-' . $id,
        ])->createView();
    }

    private static function forms(): FormFactoryInterface
    {
        $csrf = new class implements CsrfTokenManagerInterface {
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'token-' . $tokenId);
            }

            public function refreshToken(string $tokenId): CsrfToken
            {
                return $this->getToken($tokenId);
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            public function isTokenValid(CsrfToken $token): bool
            {
                return true;
            }
        };

        return Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addTypes([
                new DeleteCookieDefinitionType($csrf),
                FormKitTestSupport::withMerger(new CookieDefinitionType()),
                FormKitTestSupport::withMerger(new CookieDefinitionTranslationType()),
                FormKitTestSupport::withMerger(new CookieConsentConfigProfileSettingsType()),
                FormKitTestSupport::withMerger(new CookieConsentConfigBehaviorSettingsType()),
                FormKitTestSupport::withMerger(new CookieConsentConfigAppearanceSettingsType()),
                FormKitTestSupport::withMerger(new CookieConsentConfigConsentModalSettingsType()),
                FormKitTestSupport::withMerger(new CookieConsentConfigPreferencesModalSettingsType()),
                FormKitTestSupport::withMerger(new CookieConsentConfigRouteTargetingSettingsType()),
            ])
            ->getFormFactory();
    }

    private static function setId(object $entity, int $id): void
    {
        (new ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
