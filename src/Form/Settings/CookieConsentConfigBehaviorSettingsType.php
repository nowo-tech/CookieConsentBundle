<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Settings form for the Behavior admin section.
 */
final class CookieConsentConfigBehaviorSettingsType extends AbstractCookieConsentConfigSettingsType
{
    /** Unmapped field: does this change need every visitor to consent again? (1.25, JH-L1) */
    public const RECONSENT_FIELD = 'reconsent';

    public const RECONSENT_YES = 'yes';

    public const RECONSENT_NO = 'no';

    private const RECONSENT_TRANSLATION_DOMAIN = 'NowoCookieConsentBundle';

    private const RECONSENT_TRANSLATION_PREFIX = 'nowo_cookie_consent.admin.config.settings.fields.';

    private const RECONSENT_REQUIRED_FALLBACK = 'You changed the revision: choose whether visitors must consent again.';

    /**
     * @param FormBuilderInterface<CookieConsentConfig|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->rememberTranslationDomain($options);

        $labelPrefix  = $options['label_prefix'];
        $choicePrefix = $options['choice_label_prefix'];

        $this->addCheckbox($builder, 'autoShow', [
            'label'    => $labelPrefix . 'auto_show',
            'help'     => $labelPrefix . 'auto_show_help',
            'required' => false,
        ]);
        $this->addInteger($builder, 'revision', [
            'label' => $labelPrefix . 'revision',
            'help'  => $labelPrefix . 'revision_help',
        ]);
        $this->addReconsentChoice($builder, $options['data'] ?? null);
        $this->addCheckbox($builder, 'manageScriptTags', [
            'label'    => $labelPrefix . 'manage_script_tags',
            'help'     => $labelPrefix . 'manage_script_tags_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'autoClearCookies', [
            'label'    => $labelPrefix . 'auto_clear_cookies',
            'help'     => $labelPrefix . 'auto_clear_cookies_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'hideFromBots', [
            'label'    => $labelPrefix . 'hide_from_bots',
            'help'     => $labelPrefix . 'hide_from_bots_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'lazyHtmlGeneration', [
            'label'    => $labelPrefix . 'lazy_html_generation',
            'help'     => $labelPrefix . 'lazy_html_generation_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'manageIframePlaceholders', [
            'label'    => $labelPrefix . 'manage_iframe_placeholders',
            'help'     => $labelPrefix . 'manage_iframe_placeholders_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'granularCookieSelection', [
            'label'    => $labelPrefix . 'granular_cookie_selection',
            'help'     => $labelPrefix . 'granular_cookie_selection_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'preferencesBubbleEnabled', [
            'label'    => $labelPrefix . 'preferences_bubble_enabled',
            'help'     => $labelPrefix . 'preferences_bubble_enabled_help',
            'required' => false,
        ]);
        $this->addChoice($builder, 'preferencesBubblePosition', [
            'label'   => $labelPrefix . 'preferences_bubble_position',
            'help'    => $labelPrefix . 'preferences_bubble_position_help',
            'choices' => [
                $choicePrefix . 'bubble_position.bottom_right' => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT,
                $choicePrefix . 'bubble_position.bottom_left'  => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_LEFT,
                $choicePrefix . 'bubble_position.top_right'    => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_TOP_RIGHT,
                $choicePrefix . 'bubble_position.top_left'     => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_TOP_LEFT,
            ],
        ]);
        $this->addWithDefaults($builder, 'preferencesBubbleBorderColor', ColorType::class, [
            'label'    => $labelPrefix . 'preferences_bubble_border_color',
            'help'     => $labelPrefix . 'preferences_bubble_border_color_help',
            'required' => false,
        ]);
        $this->addTextarea($builder, 'preferencesBubbleIcon', [
            'label'    => $labelPrefix . 'preferences_bubble_icon',
            'help'     => $labelPrefix . 'preferences_bubble_icon_help',
            'required' => false,
            'attr'     => [
                'rows'       => 4,
                'spellcheck' => 'false',
            ],
        ]);
    }

    /**
     * 1.25 (JH-L1): asks whether the change needs every visitor to consent again.
     *
     * Unmapped radios with no pre-selected answer. A change of `revision` on a saved profile cannot be saved without
     * an answer; "yes" records the re-consent request ({@see CookieConsentConfig::requireReconsent()}), "no" only
     * updates the label. "Yes" also works without changing the revision. New profiles skip the question: no visitor
     * consented under them yet.
     *
     * The texts always come from the bundle domain (not `label_prefix`), so hosts that relabel the settings form
     * get them without adding keys.
     *
     * @param FormBuilderInterface<mixed> $builder The form builder
     * @param mixed $data The profile the form edits
     */
    private function addReconsentChoice(FormBuilderInterface $builder, mixed $data): void
    {
        $labelPrefix = self::RECONSENT_TRANSLATION_PREFIX;

        if (!$data instanceof CookieConsentConfig || $data->getId() === null) {
            return;
        }

        $originalRevision = $data->getRevision();
        $lastRequest      = $data->getReconsentRequiredAt();

        $this->addChoice($builder, self::RECONSENT_FIELD, [
            'label'                       => $labelPrefix . 'reconsent',
            'help'                        => $labelPrefix . ($lastRequest instanceof DateTimeImmutable ? 'reconsent_help' : 'reconsent_help_never'),
            'help_translation_parameters' => [
                '%date%' => $lastRequest?->format('Y-m-d H:i') . ' UTC',
            ],
            'translation_domain'        => self::RECONSENT_TRANSLATION_DOMAIN,
            'choice_translation_domain' => self::RECONSENT_TRANSLATION_DOMAIN,
            'mapped'                    => false,
            'required'                  => false,
            'expanded'                  => true,
            'multiple'                  => false,
            'placeholder'               => false,
            'data'                      => null,
            'choices'                   => [
                $labelPrefix . 'reconsent_no'  => self::RECONSENT_NO,
                $labelPrefix . 'reconsent_yes' => self::RECONSENT_YES,
            ],
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($originalRevision): void {
            $form   = $event->getForm();
            $config = $event->getData();

            if (!$config instanceof CookieConsentConfig || !$form->has(self::RECONSENT_FIELD)) {
                return;
            }

            $field  = $form->get(self::RECONSENT_FIELD);
            $choice = $field->getData();

            if ($choice === null && $config->getRevision() !== $originalRevision) {
                $messageKey = self::RECONSENT_TRANSLATION_PREFIX . 'reconsent_required';
                // @igor-ignore - $field is the per-request form child, not a shared service.
                $field->addError(new FormError(
                    $this->translate($messageKey, self::RECONSENT_TRANSLATION_DOMAIN, self::RECONSENT_REQUIRED_FALLBACK),
                    $messageKey,
                ));

                return;
            }

            if ($choice === self::RECONSENT_YES) {
                $config->requireReconsent(new DateTimeImmutable());
            }
        });
    }
}
