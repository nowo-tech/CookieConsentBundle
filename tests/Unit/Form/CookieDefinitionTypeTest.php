<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Symfony\Component\Form\Test\TypeTestCase;

final class CookieDefinitionTypeTest extends TypeTestCase
{
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
}
