<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Form\FormKitPlaceholders;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class FormKitPlaceholdersTest extends TestCase
{
    /**
     * Mimics FormKit: a convention attr.placeholder unless placeholder: false.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function formKit(array $options): array
    {
        $received = $options;
        unset($options['placeholder']);
        $options['attr']                 = ($received['placeholder'] ?? null) === false ? [] : ['placeholder' => 'form.field.placeholder'];
        $options['received_placeholder'] = $received['placeholder'] ?? null;

        return $options;
    }

    public function testFieldsWithoutExplicitPlaceholderGetNone(): void
    {
        $resolved = FormKitPlaceholders::resolve(TextType::class, ['label' => 'x'], $this->formKit(...));

        self::assertFalse($resolved['received_placeholder']);
        self::assertSame([], $resolved['attr']);
        self::assertArrayNotHasKey('placeholder', $resolved);
    }

    public function testCheckboxNeverGetsAPlaceholder(): void
    {
        $resolved = FormKitPlaceholders::resolve(CheckboxType::class, ['placeholder' => 'x', 'attr' => ['placeholder' => 'y']], $this->formKit(...));

        self::assertSame([], $resolved['attr']);
        self::assertArrayNotHasKey('placeholder', $resolved);
    }

    public function testExplicitAttrPlaceholderIsKept(): void
    {
        $resolved = FormKitPlaceholders::resolve(TextareaType::class, ['attr' => ['placeholder' => "a\nb"]], $this->formKit(...));

        self::assertSame(['placeholder' => "a\nb"], $resolved['attr']);
    }

    public function testChoiceRootPlaceholderStaysTheEmptyOptionLabel(): void
    {
        $resolved = FormKitPlaceholders::resolve(ChoiceType::class, ['placeholder' => 'none'], $this->formKit(...));

        self::assertSame('none', $resolved['placeholder']);
        self::assertSame([], $resolved['attr']);
    }

    public function testRootPlaceholderOnOtherTypesBecomesAttrPlaceholder(): void
    {
        $resolved = FormKitPlaceholders::resolve(TextType::class, ['placeholder' => 'hint'], $this->formKit(...));

        self::assertSame(['placeholder' => 'hint'], $resolved['attr']);
        self::assertArrayNotHasKey('placeholder', $resolved);
    }

    public function testExplicitAttrPlaceholderWinsOverRootPlaceholder(): void
    {
        $resolved = FormKitPlaceholders::resolve(TextType::class, ['placeholder' => 'root', 'attr' => ['placeholder' => 'attr']], $this->formKit(...));

        self::assertSame(['placeholder' => 'attr'], $resolved['attr']);
    }

    public function testMissingAttrArrayIsCreated(): void
    {
        $resolved = FormKitPlaceholders::resolve(TextType::class, ['placeholder' => 'hint'], static fn (array $options): array => []);

        self::assertSame(['attr' => ['placeholder' => 'hint']], $resolved);
    }
}
