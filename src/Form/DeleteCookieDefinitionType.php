<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * CSRF-protected delete form for a cookie definition row action.
 */
final class DeleteCookieDefinitionType extends AbstractType
{
    public function __construct(private readonly CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('_token', HiddenType::class, [
            'data' => $this->csrfTokenManager->getToken($options['csrf_token_id'])->getValue(),
            'mapped' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['action', 'csrf_token_id']);
        $resolver->setAllowedTypes('action', 'string');
        $resolver->setAllowedTypes('csrf_token_id', 'string');
        $resolver->setDefaults([
            'method' => 'POST',
            'attr' => ['class' => 'd-inline'],
            'csrf_protection' => false,
        ]);
    }
}
