<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Form\CookieDefinitionTranslationType;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieDefinitionTypeTest extends TypeTestCase
{
    /**
     * @return list<FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        $merger = $this->formOptionsMerger();

        $definition = new CookieDefinitionType();
        $definition->setFormOptionsMerger($merger);

        $translation = new CookieDefinitionTranslationType();
        $translation->setFormOptionsMerger($merger);

        return [$definition, $translation];
    }

    protected function getExtensions(): array
    {
        return array_merge(parent::getExtensions(), [
            new PreloadedExtension($this->getTypes(), []),
        ]);
    }

    private function formOptionsMerger(): FormOptionsMerger
    {
        return new FormOptionsMerger(
            [
                'cookie_consent' => [
                    'translation_domain' => 'NowoCookieConsentBundle',
                    'defaults'           => [
                        'attr'     => [],
                        'row_attr' => [],
                    ],
                    'field_types' => [],
                ],
            ],
            'cookie_consent',
            new ConstraintDefinitionFactory(),
        );
    }

    public function testBuildsDefinitionFields(): void
    {
        $definition = (new CookieDefinition())
            ->setName('_ga')
            ->setDuration('2 years')
            ->setCategory('analytics')
            ->setType(CookieDefinition::TYPE_THIRD_PARTY)
            ->setSortOrder(1);

        $form = $this->factory->create(CookieDefinitionType::class, $definition);
        $view = $form->createView();

        self::assertTrue($form->has('name'));
        self::assertTrue($form->has('duration'));
        self::assertTrue($form->has('category'));
        self::assertTrue($form->has('type'));
        self::assertTrue($form->has('sortOrder'));
        self::assertTrue($form->has('allowedByDefault'));
        self::assertTrue($form->has('translations'));
        self::assertSame('NowoCookieConsentBundle', $view->vars['translation_domain']);
    }

    public function testSubmitsDefinitionData(): void
    {
        $form = $this->factory->create(CookieDefinitionType::class);
        $form->submit([
            'name'             => '_gid',
            'duration'         => 'Session',
            'category'         => 'analytics',
            'type'             => CookieDefinition::TYPE_FIRST_PARTY,
            'sortOrder'        => 2,
            'allowedByDefault' => true,
            'translations'     => [],
        ]);

        self::assertTrue($form->isSynchronized());
        /** @var CookieDefinition $data */
        $data = $form->getData();
        self::assertSame('_gid', $data->getName());
        self::assertSame('analytics', $data->getCategory());
    }

    public function testLocalesDefaultToTheLocaleResolverAndTheDefaultFallsBackToTheFirstEnabledLocale(): void
    {
        $type = new CookieDefinitionType(new LocaleResolver(['es', 'en'], 'fr', false, new RequestStack()));
        $type->setFormOptionsMerger($this->formOptionsMerger());
        $translation = new CookieDefinitionTranslationType();
        $translation->setFormOptionsMerger($this->formOptionsMerger());
        $factory = Forms::createFormFactoryBuilder()->addTypes([$type, $translation])->getFormFactory();

        $view = $factory->create(CookieDefinitionType::class, new CookieDefinition())->createView();

        self::assertSame(['es', 'en'], $view->vars['enabled_locales']);
        self::assertSame('es', $view->vars['default_locale']);
        self::assertSame(['es', 'en'], array_keys($view['translations']->children));
        self::assertSame([], $view->vars['other_translations']);
    }

    public function testEmptyFormHasNoTranslationTabsData(): void
    {
        $view = $this->factory->create(CookieDefinitionType::class, null, ['enabled_locales' => ['en'], 'default_locale' => 'en'])->createView();

        self::assertSame([], $view->vars['other_translations']);
        self::assertSame('en', $view->vars['default_locale']);
    }

    public function testSubmitKeepsOtherLocalesAndIgnoresEmptyNewTabs(): void
    {
        $definition = (new CookieDefinition())->setName('_ga');
        $definition->addTranslation((new CookieDefinitionTranslation())->setLocale('de')->setProvider('Google'));
        $form = $this->factory->create(CookieDefinitionType::class, $definition, ['enabled_locales' => ['en', 'es'], 'default_locale' => 'es']);

        $form->submit([
            'name'         => '_ga',
            'category'     => 'analytics',
            'type'         => CookieDefinition::TYPE_FIRST_PARTY,
            'translations' => [
                'en' => ['locale' => 'en', 'provider' => '', 'purpose' => ''],
                'es' => ['locale' => 'xx', 'provider' => 'Google', 'purpose' => 'Analítica'],
            ],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertNull($definition->findTranslation('en'), 'an empty new tab adds nothing');
        self::assertSame('Google', $definition->findTranslation('es')?->getProvider(), 'the locale is the tab, not the submitted value');
        self::assertSame('Google', $definition->findTranslation('de')?->getProvider(), 'a locale that is not enabled is kept');
        self::assertSame(['de', 'es'], array_keys($definition->getTranslations()->toArray()));
    }
}
