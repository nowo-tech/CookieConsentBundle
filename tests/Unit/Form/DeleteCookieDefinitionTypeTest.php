<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Form\DeleteCookieDefinitionType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class DeleteCookieDefinitionTypeTest extends TypeTestCase
{
    public function testViewUsesExpectedDeleteOptions(): void
    {
        $form = $this->factory->create(DeleteCookieDefinitionType::class, null, [
            'action'        => '/cookie-consent-config/1/cookies/5/delete',
            'csrf_token_id' => 'delete-cookie-definition-5',
        ]);

        $view = $form->createView();

        self::assertSame('POST', $form->getConfig()->getMethod());
        self::assertSame('/cookie-consent-config/1/cookies/5/delete', $view->vars['action']);
        self::assertSame('d-inline', $view->vars['attr']['class']);
        self::assertArrayHasKey('_token', $view->children);
        self::assertSame('generated-token', $view->children['_token']->vars['value']);
    }

    protected function getExtensions(): array
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->with('delete-cookie-definition-5')->willReturn(new CsrfToken('delete-cookie-definition-5', 'generated-token'));

        return [
            new PreloadedExtension([
                new DeleteCookieDefinitionType($csrfTokenManager),
            ], []),
        ];
    }
}
