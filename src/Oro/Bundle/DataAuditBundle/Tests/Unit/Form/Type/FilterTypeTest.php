<?php

namespace Oro\Bundle\DataAuditBundle\Tests\Unit\Form\Type;

use Oro\Bundle\DataAuditBundle\Form\Type\FilterType as AuditFilterType;
use Oro\Bundle\FilterBundle\Form\Type\Filter\FilterType;
use Oro\Component\Testing\Unit\PreloadedExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\Type\FormTypeValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilterTypeTest extends TestCase
{
    private FormFactoryInterface $factory;

    #[\Override]
    protected function setUp(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->any())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtensions($this->getExtensions())
            ->addTypeExtension(new FormTypeValidatorExtension($validator))
            ->addTypeGuesser($this->createMock(ValidatorTypeGuesser::class))
            ->getFormFactory();
    }

    public function testSubmit(): void
    {
        $formData = [
            'filter' => [
                'data' => 'data',
                'type' => 'type',
            ],
            'auditFilter' => [
                'data'       => 'auditData',
                'type'       => 'auditType',
                'columnName' => 'c',
            ],
        ];

        $form = $this->factory->create(AuditFilterType::class);
        $form->submit($formData);

        $this->assertTrue($form->isValid());
        $this->assertTrue($form->isSynchronized());
    }

    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([
                new FilterType($this->createMock(TranslatorInterface::class))
            ], [])
        ];
    }
}
