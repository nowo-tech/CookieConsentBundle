<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
        private readonly array $cookieCategories,
        private readonly bool $csrfProtection = true,
    ) {
    }

    /**
     * Builds the consent form fields and submit handlers.
     *
     * @param FormBuilderInterface<array<string, mixed>|null> $builder The form builder
     * @param array<string, mixed> $options The form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('required', CheckboxType::class, [
            'label'    => false,
            'disabled' => true,
            'data'     => true,
        ]);

        foreach ($this->cookieCategories as $category) {
            $builder->add($category, CheckboxType::class, [
                'label' => false,
                'data'  => $this->cookieChecker->isCategoryAllowedByUser($category),
            ]);
        }

        $builder->add('save', SubmitType::class, [
            'label' => 'nowo_cookie_consent.save',
            'attr'  => ['class' => 'btn nowo-cookie-consent__btn'],
        ]);
        $builder->add('use_only_functional_cookies', SubmitType::class, [
            'label' => 'nowo_cookie_consent.use_only_functional_cookies',
            'attr'  => ['class' => 'btn nowo-cookie-consent__btn'],
        ]);
        $builder->add('use_all_cookies', SubmitType::class, [
            'label' => 'nowo_cookie_consent.use_all_cookies',
            'attr'  => ['class' => 'btn nowo-cookie-consent__btn nowo-cookie-consent__btn--secondary'],
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            foreach ($this->cookieCategories as $category) {
                if (isset($data['use_all_cookies'])) {
                    $data[$category] = true;
                } elseif (isset($data['use_only_functional_cookies'])) {
                    $data[$category] = false;
                } else {
                    $data[$category] ??= false;
                }
            }

            $event->setData($data);
        });
    }

    /**
     * Configures default options for the consent form type.
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'NowoCookieConsentBundle',
            'csrf_protection'    => $this->csrfProtection,
        ]);
    }
}
