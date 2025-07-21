<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Validator\Constraints;

use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Manager\LocalizationManager;
use Oro\Bundle\LocaleBundle\Validator\Constraints\DefaultLocalization;
use Oro\Bundle\LocaleBundle\Validator\Constraints\DefaultLocalizationValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class DefaultLocalizationValidatorTest extends ConstraintValidatorTestCase
{
    private LocalizationManager&MockObject $localizationManager;
    private FormInterface&MockObject $form;

    #[\Override]
    protected function setUp(): void
    {
        $this->localizationManager = $this->createMock(LocalizationManager::class);
        $this->form = $this->createMock(FormInterface::class);
        parent::setUp();
        $this->setRoot($this->form);
    }

    #[\Override]
    protected function createValidator(): DefaultLocalizationValidator
    {
        return new DefaultLocalizationValidator($this->localizationManager);
    }

    public function testGetTargets()
    {
        $constraint = new DefaultLocalization();
        $this->assertEquals(Constraint::CLASS_CONSTRAINT, $constraint->getTargets());
    }

    public function testValidateAndNoLocalizationForm()
    {
        $this->form->expects($this->once())
            ->method('getName')
            ->willReturn('unknown_name');

        $constraint = new DefaultLocalization();
        $this->validator->validate(1, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateAndNoEnabledLocalizationsField()
    {
        $this->form->expects($this->once())
            ->method('getName')
            ->willReturn('localization');

        $this->form->expects($this->once())
            ->method('has')
            ->with(DefaultLocalizationValidator::ENABLED_LOCALIZATIONS_NAME)
            ->willReturn(false);

        $constraint = new DefaultLocalization();
        $this->validator->validate(1, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateAndValueInEnabledLocalizations()
    {
        $this->form->expects($this->once())
            ->method('getName')
            ->willReturn('localization');
        $this->form->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->form->expects($this->once())
            ->method('getData')
            ->willReturn([
                DefaultLocalizationValidator::ENABLED_LOCALIZATIONS_NAME => [
                    'value' => [1, 2, 3]
                ],
            ]);

        $constraint = new DefaultLocalization();
        $this->validator->validate(1, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateAndNotEnabledLocalization()
    {
        $this->form->expects($this->once())
            ->method('getName')
            ->willReturn('localization');
        $this->form->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->form->expects($this->once())
            ->method('getData')
            ->willReturn([
                DefaultLocalizationValidator::ENABLED_LOCALIZATIONS_NAME => [
                    'value' => [2, 3, 4]
                ],
            ]);

        $this->localizationManager->expects($this->once())
            ->method('getLocalization')
            ->willReturn((new Localization())->setName('L1'));

        $constraint = new DefaultLocalization();
        $this->validator->validate(1, $constraint);

        $this->buildViolation('oro.locale.validators.is_not_enabled')
            ->setParameter('%localization%', 'L1')
            ->assertRaised();
    }

    public function testValidateAndUnknownLocalization()
    {
        $this->form->expects($this->once())
            ->method('getName')
            ->willReturn('localization');
        $this->form->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->form->expects($this->once())
            ->method('getData')
            ->willReturn([
                DefaultLocalizationValidator::ENABLED_LOCALIZATIONS_NAME => [
                    'value' => [2, 3, 4]
                ],
            ]);

        $this->localizationManager->expects($this->once())
            ->method('getLocalization')
            ->willReturn(null);

        $constraint = new DefaultLocalization();
        $this->validator->validate(1, $constraint);

        $this->buildViolation('oro.locale.validators.unknown_localization')
            ->setParameter('%localization_id%', 1)
            ->assertRaised();
    }
}
