<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigBehaviorSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigFullSettingsType;
use Nowo\CookieConsentBundle\Tests\Support\FormKitTestSupport;
use ReflectionProperty;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Owner decision JH-L1 (1.25): when the admin changes the revision of a saved profile, they must say whether visitors
 * have to consent again. "Yes" records the re-consent request, "no" only updates the label, and no answer refuses the
 * save.
 */
final class BehaviorSettingsReconsentTest extends TypeTestCase
{
    /**
     * @return list<object>
     */
    protected function getTypes(): array
    {
        $behavior = FormKitTestSupport::withMerger(new CookieConsentConfigBehaviorSettingsType());
        $behavior->setCookieConsentTranslator($this->translator());

        $full = FormKitTestSupport::withMerger(new CookieConsentConfigFullSettingsType());
        $full->setCookieConsentTranslator($this->translator());

        return [$behavior, $full];
    }

    public function testQuestionHasNoPreselectedAnswer(): void
    {
        $form = $this->factory->create(CookieConsentConfigBehaviorSettingsType::class, $this->savedProfile(3));
        $view = $form->createView();

        self::assertTrue($form->has('reconsent'));
        self::assertNull($form->get('reconsent')->getData());
        self::assertTrue($view['reconsent']->vars['expanded']);
        self::assertSame('nowo_cookie_consent.admin.config.settings.fields.reconsent_help_never', $view['reconsent']->vars['help']);
        foreach ($view['reconsent']->children as $radio) {
            self::assertFalse($radio->vars['checked']);
        }
    }

    public function testHelpShowsTheLastRequest(): void
    {
        $profile = $this->savedProfile(3)->requireReconsent(new DateTimeImmutable('2026-11-09T10:15:00+01:00'));
        $view    = $this->factory->create(CookieConsentConfigBehaviorSettingsType::class, $profile)->createView();

        self::assertSame('nowo_cookie_consent.admin.config.settings.fields.reconsent_help', $view['reconsent']->vars['help']);
        self::assertSame(['%date%' => '2026-11-09 09:15 UTC'], $view['reconsent']->vars['help_translation_parameters']);
    }

    public function testRevisionChangeWithoutAnswerIsRefused(): void
    {
        $profile = $this->savedProfile(3);
        $form    = $this->submit($profile, ['revision' => '4']);

        self::assertFalse($form->isValid());
        $errors = $form->get('reconsent')->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('translated:nowo_cookie_consent.admin.config.settings.fields.reconsent_required', $errors[0]->getMessage());
        self::assertSame('nowo_cookie_consent.admin.config.settings.fields.reconsent_required', $errors[0]->getMessageTemplate());
        self::assertNull($profile->getReconsentRequiredAt());
    }

    public function testRevisionChangeAnsweredNoOnlyUpdatesTheLabel(): void
    {
        $profile = $this->savedProfile(3);
        $form    = $this->submit($profile, ['revision' => '4', 'reconsent' => 'no']);

        self::assertTrue($form->isValid());
        self::assertSame(4, $profile->getRevision());
        self::assertNull($profile->getReconsentRequiredAt());
    }

    public function testRevisionChangeAnsweredYesRecordsTheRequest(): void
    {
        $profile = $this->savedProfile(3);
        $before  = time();
        $form    = $this->submit($profile, ['revision' => '4', 'reconsent' => 'yes']);

        self::assertTrue($form->isValid());
        self::assertSame(4, $profile->getRevision());
        $requiredAt = $profile->getReconsentRequiredAt();
        self::assertInstanceOf(DateTimeImmutable::class, $requiredAt);
        self::assertGreaterThanOrEqual($before, $requiredAt->getTimestamp());
        self::assertLessThanOrEqual(time(), $requiredAt->getTimestamp());
    }

    public function testYesWithoutChangingTheRevisionAlsoRecordsTheRequest(): void
    {
        $profile = $this->savedProfile(3);
        $form    = $this->submit($profile, ['revision' => '3', 'reconsent' => 'yes']);

        self::assertTrue($form->isValid());
        self::assertSame(3, $profile->getRevision());
        self::assertNotNull($profile->getReconsentRequiredAt());
    }

    public function testUnchangedRevisionNeedsNoAnswer(): void
    {
        $profile = $this->savedProfile(3);
        $form    = $this->submit($profile, ['revision' => '3']);

        self::assertTrue($form->isValid());
        self::assertNull($profile->getReconsentRequiredAt());
    }

    public function testUnknownAnswerIsInvalid(): void
    {
        $profile = $this->savedProfile(3);
        $form    = $this->submit($profile, ['revision' => '4', 'reconsent' => 'maybe']);

        self::assertFalse($form->isValid());
        self::assertNull($profile->getReconsentRequiredAt());
    }

    public function testNewProfileHasNoQuestion(): void
    {
        $form = $this->factory->create(CookieConsentConfigBehaviorSettingsType::class, new CookieConsentConfig());

        self::assertFalse($form->has('reconsent'));
    }

    public function testFullSettingsFormAsksToo(): void
    {
        $profile = $this->savedProfile(1);
        $form    = $this->factory->create(CookieConsentConfigFullSettingsType::class, $profile);
        self::assertTrue($form->has('reconsent'));

        $form->submit(['revision' => '2', 'consentModalLayout' => 'box'], false);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('reconsent')->getErrors());
    }

    public function testWithoutTranslatorTheEnglishMessageIsUsed(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addType(FormKitTestSupport::withMerger(new CookieConsentConfigBehaviorSettingsType()))
            ->getFormFactory();
        $form = $factory->create(CookieConsentConfigBehaviorSettingsType::class, $this->savedProfile(1));
        $form->submit(['revision' => '2'], false);

        self::assertSame('You changed the revision: choose whether visitors must consent again.', $form->get('reconsent')->getErrors()[0]->getMessage());
    }

    /**
     * @param array<string, string> $data
     *
     * @return FormInterface<mixed>
     */
    private function submit(CookieConsentConfig $profile, array $data): FormInterface
    {
        $form = $this->factory->create(CookieConsentConfigBehaviorSettingsType::class, $profile);
        $form->submit($data, false);

        return $form;
    }

    private function savedProfile(int $revision): CookieConsentConfig
    {
        $profile = (new CookieConsentConfig())->setName('Site')->setRevision($revision);
        (new ReflectionProperty(CookieConsentConfig::class, 'id'))->setValue($profile, 7);

        return $profile;
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'translated:' . $id);

        return $translator;
    }
}
