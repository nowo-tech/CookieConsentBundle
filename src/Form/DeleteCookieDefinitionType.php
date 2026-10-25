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
 *
 * @extends AbstractType<null>
 */
final class DeleteCookieDefinitionType extends AbstractType
{
    /**
     * Base form name (and 1.x POST payload key `delete_cookie_definition[_token]`). The admin list names each row
     * form {@see self::formName()} so the hidden token inputs get unique DOM ids.
     */
    public const FORM_NAME = 'delete_cookie_definition';

    /**
     * Name of the delete form of one row (`delete_cookie_definition_<id>`; payload `delete_cookie_definition_<id>[_token]`).
     */
    public static function formName(int $definitionId): string
    {
        return self::FORM_NAME . '_' . $definitionId;
    }

    /**
     * Uses the CSRF token manager to prefill the delete token.
     */
    public function __construct(private readonly CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    /**
     * Adds the hidden CSRF token of the delete form.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('_token', HiddenType::class, [
            'data'   => $this->csrfTokenManager->getToken($options['csrf_token_id'])->getValue(),
            'mapped' => false,
        ]);
    }

    /**
     * Returns the base form name shared by every row delete form.
     */
    public function getBlockPrefix(): string
    {
        return self::FORM_NAME;
    }

    /**
     * Requires the form action and the CSRF token id.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['action', 'csrf_token_id']);
        $resolver->setAllowedTypes('action', 'string');
        $resolver->setAllowedTypes('csrf_token_id', 'string');
        $resolver->setDefaults([
            'method'          => 'POST',
            'csrf_protection' => false,
        ]);
    }
}
