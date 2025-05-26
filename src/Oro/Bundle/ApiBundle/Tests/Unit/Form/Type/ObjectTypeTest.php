<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Form\Type;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Form\FormHelper;
use Oro\Bundle\ApiBundle\Form\Guesser\DataTypeGuesser;
use Oro\Bundle\ApiBundle\Form\Type\ObjectType;
use Oro\Bundle\ApiBundle\Metadata\AssociationMetadata;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Metadata\FieldMetadata;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\FormType\NameContainerType;
use Oro\Bundle\ApiBundle\Tests\Unit\Form\ApiFormTypeTestCase;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ObjectTypeTest extends ApiFormTypeTestCase
{
    #[\Override]
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension(
                [new ObjectType($this->getFormHelper())],
                $this->getApiTypeExtensions()
            )
        ];
    }

    private function getFormHelper(): FormHelper
    {
        return new FormHelper(
            $this->createMock(FormFactoryInterface::class),
            $this->createMock(DataTypeGuesser::class),
            $this->createMock(PropertyAccessorInterface::class),
            $this->createMock(ContainerInterface::class)
        );
    }

    public function testBuildFormForField(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('name'));

        $config = new EntityDefinitionConfig();
        $config->addField('name');

        $data = new Entity\User();
        $form = $this->factory->create(
            ObjectType::class,
            $data,
            [
                'data_class' => Entity\User::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form->submit(['name' => 'testName']);
        self::assertTrue($form->isSynchronized());
        self::assertEquals('testName', $data->getName());
    }

    public function testBuildFormForRenamedField(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('renamedName'))
            ->setPropertyPath('name');

        $config = new EntityDefinitionConfig();
        $config->addField('renamedName')->setPropertyPath('name');

        $data = new Entity\User();
        $form = $this->factory->create(
            ObjectType::class,
            $data,
            [
                'data_class' => Entity\User::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form->submit(['renamedName' => 'testName']);
        self::assertTrue($form->isSynchronized());
        self::assertEquals('testName', $data->getName());
    }

    public function testBuildFormForFieldWithFormType(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('id'));

        $config = new EntityDefinitionConfig();
        $config->addField('id')->setFormType(IntegerType::class);

        $data = new Entity\User();
        $form = $this->factory->create(
            ObjectType::class,
            $data,
            [
                'data_class' => Entity\User::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form->submit(['id' => '123']);
        self::assertTrue($form->isSynchronized());
        self::assertSame(123, $data->getId());
    }

    public function testBuildFormForFieldWithFormOptions(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('renamedName'));

        $config = new EntityDefinitionConfig();
        $config->addField('renamedName')->setFormOptions(['property_path' => 'name']);

        $data = new Entity\User();
        $form = $this->factory->create(
            ObjectType::class,
            $data,
            [
                'data_class' => Entity\User::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form->submit(['renamedName' => 'testName']);
        self::assertTrue($form->isSynchronized());
        self::assertEquals('testName', $data->getName());
    }

    public function testBuildFormForIgnoredField(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('name'))
            ->setPropertyPath(ConfigUtil::IGNORE_PROPERTY_PATH);

        $config = new EntityDefinitionConfig();
        $config->addField('name')->setPropertyPath(ConfigUtil::IGNORE_PROPERTY_PATH);

        $data = new Entity\User();
        $form = $this->factory->create(
            ObjectType::class,
            $data,
            [
                'data_class' => Entity\User::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form->submit(['name' => 'testName']);
        self::assertTrue($form->isSynchronized());
        self::assertNull($data->getName());
    }

    public function testBuildFormForFieldIgnoredOnlyForGetActions(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('name'));

        $config = new EntityDefinitionConfig();
        $fieldConfig = $config->addField('name');
        $fieldConfig->setPropertyPath(ConfigUtil::IGNORE_PROPERTY_PATH);

        $data = new Entity\User();
        $form = $this->factory->create(
            ObjectType::class,
            $data,
            [
                'data_class' => Entity\User::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form->submit(['name' => 'testName']);
        self::assertTrue($form->isSynchronized());
        self::assertEquals('testName', $data->getName());
    }

    public function testBuildFormForAssociation(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addAssociation(new AssociationMetadata('owner'))->setDataType('integer');

        $config = new EntityDefinitionConfig();
        $field = $config->addField('owner');
        $field->setFormType(NameContainerType::class);
        $field->setFormOptions(['data_class' => Entity\User::class]);

        $data = new Entity\User();
        $form = $this->factory->create(
            ObjectType::class,
            $data,
            [
                'data_class' => Entity\User::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form->submit(['owner' => ['name' => 'testName']]);
        self::assertTrue($form->isSynchronized());
        self::assertNotNull($data->getOwner());
        self::assertSame('testName', $data->getOwner()->getName());
    }

    public function testCreateNestedObject(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('value'));
        $metadata->addField(new FieldMetadata('currency'));

        $config = new EntityDefinitionConfig();
        $config->addField('value');
        $config->addField('currency');

        $data = new Entity\Product();
        $formBuilder = $this->factory->createBuilder(
            FormType::class,
            $data,
            ['data_class' => Entity\Product::class]
        );
        $formBuilder->add(
            'price',
            ObjectType::class,
            [
                'data_class' => Entity\ProductPrice::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form = $formBuilder->getForm();
        $form->submit(['price' => ['value' => 'testPriceValue']]);
        self::assertTrue($form->isSynchronized());
        self::assertSame('testPriceValue', $data->getPrice()->getValue());
    }

    public function testCreateNestedObjectWhenValueIsNotSubmitted(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('value'));
        $metadata->addField(new FieldMetadata('currency'));

        $config = new EntityDefinitionConfig();
        $config->addField('value');
        $config->addField('currency');

        $data = new Entity\Product();
        $formBuilder = $this->factory->createBuilder(
            FormType::class,
            $data,
            ['data_class' => Entity\Product::class]
        );
        $formBuilder->add(
            'price',
            ObjectType::class,
            [
                'data_class' => Entity\ProductPrice::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form = $formBuilder->getForm();
        $form->submit([]);
        self::assertTrue($form->isSynchronized());
        self::assertNull($data->getPrice()->getValue());
    }

    public function testCreateNestedObjectWhenSubmittedValueIsNull(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('value'));
        $metadata->addField(new FieldMetadata('currency'));

        $config = new EntityDefinitionConfig();
        $config->addField('value');
        $config->addField('currency');

        $data = new Entity\Product();
        $formBuilder = $this->factory->createBuilder(
            FormType::class,
            $data,
            ['data_class' => Entity\Product::class]
        );
        $formBuilder->add(
            'price',
            ObjectType::class,
            [
                'data_class' => Entity\ProductPrice::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form = $formBuilder->getForm();
        $form->submit(['price' => null]);
        self::assertTrue($form->isSynchronized());
        self::assertNull($data->getPrice()->getValue());
    }

    public function testCreateNestedObjectWhenSubmittedValueIsEmptyArray(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('value'));
        $metadata->addField(new FieldMetadata('currency'));

        $config = new EntityDefinitionConfig();
        $config->addField('value');
        $config->addField('currency');

        $data = new Entity\Product();
        $formBuilder = $this->factory->createBuilder(
            FormType::class,
            $data,
            ['data_class' => Entity\Product::class]
        );
        $formBuilder->add(
            'price',
            ObjectType::class,
            [
                'data_class' => Entity\ProductPrice::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form = $formBuilder->getForm();
        $form->submit(['price' => []]);
        self::assertTrue($form->isSynchronized());
        self::assertNull($data->getPrice()->getValue());
    }

    public function testUpdateNestedObject(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('value'));
        $metadata->addField(new FieldMetadata('currency'));

        $config = new EntityDefinitionConfig();
        $config->addField('value');
        $config->addField('currency');

        $data = new Entity\Product();
        $data->setPrice(new Entity\ProductPrice('oldPriceValue', 'oldPriceCurrency'));
        $formBuilder = $this->factory->createBuilder(
            FormType::class,
            $data,
            ['data_class' => Entity\Product::class]
        );
        $formBuilder->add(
            'price',
            ObjectType::class,
            [
                'data_class' => Entity\ProductPrice::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form = $formBuilder->getForm();
        $form->submit(['price' => ['value' => 'newPriceValue']], false);
        self::assertTrue($form->isSynchronized());
        self::assertSame('newPriceValue', $data->getPrice()->getValue());
        self::assertSame('oldPriceCurrency', $data->getPrice()->getCurrency());
    }

    public function testUpdateNestedObjectWhenValueIsNotSubmitted(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('value'));
        $metadata->addField(new FieldMetadata('currency'));

        $config = new EntityDefinitionConfig();
        $config->addField('value');
        $config->addField('currency');

        $data = new Entity\Product();
        $data->setPrice(new Entity\ProductPrice('oldPriceValue', 'oldPriceCurrency'));
        $formBuilder = $this->factory->createBuilder(
            FormType::class,
            $data,
            ['data_class' => Entity\Product::class]
        );
        $formBuilder->add(
            'price',
            ObjectType::class,
            [
                'data_class' => Entity\ProductPrice::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form = $formBuilder->getForm();
        $form->submit([], false);
        self::assertTrue($form->isSynchronized());
        self::assertSame('oldPriceValue', $data->getPrice()->getValue());
        self::assertSame('oldPriceCurrency', $data->getPrice()->getCurrency());
    }

    public function testUpdateNestedObjectWhenSubmittedValueIsEmptyArray(): void
    {
        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('value'));
        $metadata->addField(new FieldMetadata('currency'));

        $config = new EntityDefinitionConfig();
        $config->addField('value');
        $config->addField('currency');

        $data = new Entity\Product();
        $data->setPrice(new Entity\ProductPrice('oldPriceValue', 'oldPriceCurrency'));
        $formBuilder = $this->factory->createBuilder(
            FormType::class,
            $data,
            ['data_class' => Entity\Product::class]
        );
        $formBuilder->add(
            'price',
            ObjectType::class,
            [
                'data_class' => Entity\ProductPrice::class,
                'metadata'   => $metadata,
                'config'     => $config
            ]
        );
        $form = $formBuilder->getForm();
        $form->submit(['price' => []], false);
        self::assertTrue($form->isSynchronized());
        self::assertSame('oldPriceValue', $data->getPrice()->getValue());
        self::assertSame('oldPriceCurrency', $data->getPrice()->getCurrency());
    }
}
