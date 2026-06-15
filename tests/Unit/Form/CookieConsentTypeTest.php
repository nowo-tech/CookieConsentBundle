<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieConsentTypeTest extends TypeTestCase
{
    public function testBuildsCategoryFieldsAndSubmitButtons(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $view = $form->createView();

        self::assertTrue($form->has('required'));
        self::assertTrue($form->has('analytics'));
        self::assertTrue($form->has('marketing'));
        self::assertTrue($form->has('save'));
        self::assertTrue($form->has('use_all_cookies'));
        self::assertTrue($form->has('use_only_functional_cookies'));
        self::assertSame('NowoCookieConsentBundle', $view->vars['translation_domain']);
    }

    public function testPreSubmitSetsAllCategoriesWhenAcceptAllClicked(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'        => '1',
            'use_all_cookies' => '',
        ]);

        self::assertTrue($form->get('analytics')->getData());
        self::assertTrue($form->get('marketing')->getData());
    }

    public function testPreSubmitClearsOptionalCategoriesForFunctionalOnly(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'                    => '1',
            'use_only_functional_cookies' => '',
        ]);

        self::assertFalse($form->get('analytics')->getData());
        self::assertFalse($form->get('marketing')->getData());
    }

    public function testPreSubmitKeepsExplicitCategorySelectionOnSave(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'  => '1',
            'analytics' => '1',
            'save'      => '',
        ]);

        self::assertTrue($form->get('analytics')->getData());
        self::assertFalse($form->get('marketing')->getData());
    }

    protected function getExtensions(): array
    {
        return [];
    }

    /**
     * @return list<\Symfony\Component\Form\FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');

        $stack = new RequestStack();
        $stack->push($request);

        return [
            new CookieConsentType(new CookieChecker($stack), ['analytics', 'marketing'], true),
        ];
    }
}
