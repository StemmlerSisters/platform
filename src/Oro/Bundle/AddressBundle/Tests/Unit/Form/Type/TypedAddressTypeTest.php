<?php

namespace Oro\Bundle\AddressBundle\Tests\Unit\Form\Type;

use Oro\Bundle\AddressBundle\Form\EventListener\FixAddressesPrimarySubscriber;
use Oro\Bundle\AddressBundle\Form\EventListener\FixAddressesTypesSubscriber;
use Oro\Bundle\AddressBundle\Form\Type\AddressType;
use Oro\Bundle\AddressBundle\Form\Type\TypedAddressType;
use Oro\Bundle\TranslationBundle\Form\Type\TranslatableEntityType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilder;

class TypedAddressTypeTest extends TestCase
{
    private TypedAddressType $type;

    #[\Override]
    protected function setUp(): void
    {
        $this->type = new TypedAddressType();
    }

    /**
     * @dataProvider buildFormDataProvider
     */
    public function testBuildForm(array $options, bool $expectAddSubscriber): void
    {
        $builder = $this->createMock(FormBuilder::class);

        if ($expectAddSubscriber) {
            $builder->expects($this->exactly(2))
                ->method('addEventSubscriber')
                ->withConsecutive(
                    [$this->isInstanceOf(FixAddressesPrimarySubscriber::class)],
                    [$this->isInstanceOf(FixAddressesTypesSubscriber::class)]
                )
                ->willReturnSelf();
        }

        $builder->expects($this->exactly(2))
            ->method('add')
            ->withConsecutive(
                [
                    'types',
                    TranslatableEntityType::class,
                    [
                        'class'        => \Oro\Bundle\AddressBundle\Entity\AddressType::class,
                        'choice_label' => 'label',
                        'required'     => false,
                        'multiple'     => true,
                        'expanded'     => true,
                    ]
                ],
                [
                    'primary',
                    CheckboxType::class,
                    [
                        'required' => false
                    ]
                ]
            )
            ->willReturnSelf();

        $this->type->buildForm($builder, $options);
    }

    public function buildFormDataProvider(): array
    {
        return [
            [
                'options'             => [
                    'single_form'                 => false,
                    'all_addresses_property_path' => null,
                ],
                'expectAddSubscriber' => false
            ],
            [
                'options'             => [
                    'single_form'                 => true,
                    'all_addresses_property_path' => null,
                ],
                'expectAddSubscriber' => false
            ],
            [
                'options'             => [
                    'single_form'                 => true,
                    'all_addresses_property_path' => 'owner.addresses',
                ],
                'expectAddSubscriber' => true
            ]
        ];
    }

    public function testGetParent(): void
    {
        $this->assertEquals(AddressType::class, $this->type->getParent());
    }

    public function testGetName(): void
    {
        $this->assertEquals('oro_typed_address', $this->type->getName());
    }
}
