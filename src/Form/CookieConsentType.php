<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony form type for the cookie consent modal.
 *
 * @extends AbstractType<array<string, mixed>|null>
 */
class CookieConsentType extends AbstractType
{
    /**
     * Creates a new cookie consent form type.
     *
     * @param list<string> $cookieCategories
     */
    public function __construct(
        private readonly CookieChecker $cookieChecker,
        private readonly CookieConsentConfigResolver $configResolver,
        private readonly CookieInventoryProvider $inventoryProvider,
        private readonly RequestStack $requestStack,
        private readonly array $cookieCategories,
        private readonly bool $csrfProtection = true,
    ) {
    }

    /**
     * Builds the consent form fields and submit handlers.
     *
     * @param FormBuilderInterface<array<string, mixed>|null> $builder The form builder
     * @param array<string, mixed> $options The form options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('required', CheckboxType::class, [
            'label'    => false,
            'disabled' => true,
            'data'     => true,
        ]);

        $inventory = $this->resolveOptionalCookieInventory();

        foreach ($this->cookieCategories as $category) {
            $builder->add($category, CheckboxType::class, [
                'label' => false,
                'data'  => $this->resolveInitialCategoryAllowed($category, $inventory),
            ]);
        }

        if ($inventory !== []) {
            $cookiesBuilder = $builder->create('cookies', FormType::class, [
                'label'    => false,
                'compound' => true,
            ]);

            foreach ($inventory as $row) {
                $cookiesBuilder->add($row['name'], CheckboxType::class, [
                    'label' => false,
                    'data'  => $this->resolveInitialCookieAllowed($row),
                ]);
            }

            $builder->add($cookiesBuilder);
        }

        $builder->add('save', SubmitType::class, [
            'label' => 'nowo_cookie_consent.save',
            'attr'  => ['class' => 'btn nowo-cookie-consent__btn nowo-cookie-consent__btn--secondary'],
        ]);
        $builder->add('use_only_functional_cookies', SubmitType::class, [
            'label' => 'nowo_cookie_consent.use_only_functional_cookies',
            'attr'  => ['class' => 'btn nowo-cookie-consent__btn nowo-cookie-consent__btn--secondary'],
        ]);
        $builder->add('use_all_cookies', SubmitType::class, [
            'label' => 'nowo_cookie_consent.use_all_cookies',
            'attr'  => ['class' => 'btn nowo-cookie-consent__btn'],
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($inventory): void {
            $data = $event->getData();

            if (!is_array($data)) {
                return;
            }

            $cookieNames = array_column($inventory, 'name');

            if (isset($data['use_all_cookies'])) {
                foreach ($this->cookieCategories as $category) {
                    $data[$category] = true;
                }

                foreach ($cookieNames as $cookieName) {
                    $data['cookies'][$cookieName] = true;
                }
            } elseif (isset($data['use_only_functional_cookies'])) {
                foreach ($this->cookieCategories as $category) {
                    $data[$category] = false;
                }

                foreach ($cookieNames as $cookieName) {
                    $data['cookies'][$cookieName] = false;
                }
            } elseif ($inventory !== []) {
                /** @var array<string, mixed> $cookieValues */
                $cookieValues = is_array($data['cookies'] ?? null) ? $data['cookies'] : [];

                foreach ($cookieNames as $cookieName) {
                    $value = $cookieValues[$cookieName] ?? false;
                    $data['cookies'][$cookieName] = $value === true || $value === 'true' || $value === '1' || $value === 1;
                }

                $data = $this->syncCategoriesFromGranularCookies($data, $inventory);
            } else {
                foreach ($this->cookieCategories as $category) {
                    $data[$category] ??= false;
                }
            }

            $event->setData($data);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{name: string, category: string, allowed_by_default?: bool}> $inventory
     *
     * @return array<string, mixed>
     */
    private function syncCategoriesFromGranularCookies(array $data, array $inventory): array
    {
        /** @var array<string, mixed> $cookieValues */
        $cookieValues = is_array($data['cookies'] ?? null) ? $data['cookies'] : [];

        foreach ($this->cookieCategories as $category) {
            $data[$category] = false;

            foreach ($inventory as $row) {
                if ($row['category'] !== $category) {
                    continue;
                }

                $value = $cookieValues[$row['name']] ?? false;
                $allowed = $value === true || $value === 'true' || $value === '1' || $value === 1;

                if ($allowed) {
                    $data[$category] = true;
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * @return list<array{name: string, category: string}>
     */
    private function resolveOptionalCookieInventory(): array
    {
        $config = $this->resolveActiveConfig();

        if (!$config instanceof CookieConsentConfig || !$config->isGranularCookieSelection()) {
            return [];
        }

        $locale = $this->requestStack->getMainRequest()?->getLocale() ?? 'en';
        $inventory = [];

        foreach ($this->inventoryProvider->listForLocale($config, $locale) as $row) {
            if ($row['category'] === 'required') {
                continue;
            }

            $inventory[] = [
                'name'               => $row['name'],
                'category'           => $row['category'],
                'allowed_by_default' => $row['allowed_by_default'] ?? true,
            ];
        }

        return $inventory;
    }

    private function resolveActiveConfig(): ?CookieConsentConfig
    {
        $request = $this->requestStack->getMainRequest();

        if ($request !== null) {
            $resolved = $request->attributes->get('nowo_cookie_consent_config');

            if ($resolved instanceof ResolvedCookieConsentConfig) {
                return $resolved->getConfig();
            }
        }

        $locale = $request?->getLocale() ?? 'en';
        $route  = $request?->attributes->get('_route');
        $route  = is_string($route) && $route !== '' ? $route : null;

        return $this->configResolver->resolve($locale, $route)?->getConfig();
    }

    /**
     * @param array{name: string, category: string, allowed_by_default?: bool} $row
     */
    private function resolveInitialCookieAllowed(array $row): bool
    {
        if ($row['category'] === 'required') {
            return true;
        }

        if ($this->cookieChecker->isCookieConsentSavedByUser()) {
            return $this->cookieChecker->isCookieAllowedByUser($row['name'], $row['category']);
        }

        return $row['allowed_by_default'] ?? true;
    }

    /**
     * @param list<array{name: string, category: string, allowed_by_default?: bool}> $inventory
     */
    private function resolveInitialCategoryAllowed(string $category, array $inventory): bool
    {
        if ($this->cookieChecker->isCookieConsentSavedByUser()) {
            return $this->cookieChecker->isCategoryAllowedByUser($category);
        }

        if ($inventory === []) {
            return false;
        }

        foreach ($inventory as $row) {
            if ($row['category'] === $category && ($row['allowed_by_default'] ?? true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configures default options for the consent form type.
     *
     * @param OptionsResolver $resolver The options resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'NowoCookieConsentBundle',
            'csrf_protection'    => $this->csrfProtection,
        ]);
    }
}
