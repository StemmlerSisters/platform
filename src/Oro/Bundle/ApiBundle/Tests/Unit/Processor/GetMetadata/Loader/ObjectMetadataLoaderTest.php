<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetMetadata\Loader;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Metadata\AssociationMetadata;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Metadata\FieldMetadata;
use Oro\Bundle\ApiBundle\Metadata\MetaPropertyMetadata;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\Loader\ObjectMetadataFactory;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\Loader\ObjectMetadataLoader;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\Loader\ObjectNestedAssociationMetadataFactory;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\Loader\ObjectNestedObjectMetadataFactory;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ObjectMetadataLoaderTest extends LoaderTestCase
{
    private ObjectMetadataFactory&MockObject $objectMetadataFactory;
    private ObjectNestedObjectMetadataFactory&MockObject $nestedObjectMetadataFactory;
    private ObjectNestedAssociationMetadataFactory&MockObject $nestedAssociationMetadataFactory;
    private ObjectMetadataLoader $objectMetadataLoader;

    #[\Override]
    protected function setUp(): void
    {
        $this->objectMetadataFactory = $this->createMock(ObjectMetadataFactory::class);
        $this->nestedObjectMetadataFactory = $this->createMock(ObjectNestedObjectMetadataFactory::class);
        $this->nestedAssociationMetadataFactory = $this->createMock(ObjectNestedAssociationMetadataFactory::class);

        $this->objectMetadataLoader = new ObjectMetadataLoader(
            $this->objectMetadataFactory,
            $this->nestedObjectMetadataFactory,
            $this->nestedAssociationMetadataFactory
        );
    }

    public function testForExcludedField(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');

        $fieldName = 'testField';
        $field = $config->addField($fieldName);
        $field->setExcluded();

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::never())
            ->method('createAndAddFieldMetadata');

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
    }

    public function testForExcludedFieldWhenExcludedPropertiesShouldNotBeIgnored(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = true;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');

        $fieldName = 'testField';
        $field = $config->addField($fieldName);
        $field->setExcluded();

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::once())
            ->method('createAndAddFieldMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            );

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
    }

    public function testForField(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');

        $fieldName = 'testField';
        $field = $config->addField($fieldName);

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::once())
            ->method('createAndAddFieldMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            );

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
    }

    public function testForMetaProperty(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');

        $fieldName = 'testField';
        $field = $config->addField($fieldName);
        $field->setMetaProperty(true);

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::once())
            ->method('createAndAddMetaPropertyMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            );

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
        self::assertFalse($entityMetadata->isInheritedType());
    }

    public function testForClassMetaProperty(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');

        $fieldName = '__class__';
        $field = $config->addField($fieldName);
        $field->setMetaProperty(true);

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::once())
            ->method('createAndAddMetaPropertyMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            );

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
        self::assertTrue($entityMetadata->isInheritedType());
    }

    public function testForAssociation(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');

        $fieldName = 'testField';
        $field = $config->addField($fieldName);
        $field->setTargetClass('Test\TargetClass');

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::once())
            ->method('createAndAddAssociationMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                self::identicalTo($config),
                $fieldName,
                self::identicalTo($field),
                $targetAction
            );

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
    }

    public function testForNestedObject(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');

        $fieldName = 'testField';
        $field = $config->addField($fieldName);
        $field->setDataType('nestedObject');

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->nestedObjectMetadataFactory->expects(self::once())
            ->method('createAndAddNestedObjectMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                self::identicalTo($config),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $withExcludedProperties,
                $targetAction
            );

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
    }

    public function testForNestedAssociation(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');

        $fieldName = 'testField';
        $field = $config->addField($fieldName);
        $field->setDataType('nestedAssociation');

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->nestedAssociationMetadataFactory->expects(self::once())
            ->method('createAndAddNestedAssociationMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $withExcludedProperties,
                $targetAction
            );

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
    }

    public function testForFieldWithConfiguredDirection(): void
    {
        $entityClass = 'Test\Class';
        $fieldName = 'testField';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $entityMetadata = new EntityMetadata('Test\Entity');
        $fieldMetadata = new FieldMetadata($fieldName);

        $field = $config->addField($fieldName);
        $field->setDirection('output-only');

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::once())
            ->method('createAndAddFieldMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($fieldMetadata);

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
        self::assertFalse($fieldMetadata->isInput());
        self::assertTrue($fieldMetadata->isOutput());
    }

    public function testForMetaPropertyWithConfiguredDirection(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';
        $fieldName = 'testField';

        $entityMetadata = new EntityMetadata('Test\Entity');
        $propertyMetadata = new MetaPropertyMetadata($fieldName);

        $field = $config->addField($fieldName);
        $field->setMetaProperty(true);
        $field->setDirection('output-only');

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::once())
            ->method('createAndAddMetaPropertyMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($propertyMetadata);

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
        self::assertFalse($entityMetadata->isInheritedType());
        self::assertFalse($propertyMetadata->isInput());
        self::assertTrue($propertyMetadata->isOutput());
    }

    public function testForAssociationWithConfiguredDirection(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';
        $fieldName = 'testField';

        $entityMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata($fieldName);

        $field = $config->addField($fieldName);
        $field->setTargetClass('Test\TargetClass');
        $field->setDirection('output-only');

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->objectMetadataFactory->expects(self::once())
            ->method('createAndAddAssociationMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                self::identicalTo($config),
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($associationMetadata);

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
        self::assertFalse($associationMetadata->isInput());
        self::assertTrue($associationMetadata->isOutput());
    }

    public function testForNestedObjectWithConfiguredDirection(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';
        $fieldName = 'testField';

        $entityMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata($fieldName);

        $field = $config->addField($fieldName);
        $field->setDataType('nestedObject');
        $field->setDirection('output-only');

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->nestedObjectMetadataFactory->expects(self::once())
            ->method('createAndAddNestedObjectMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                self::identicalTo($config),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $withExcludedProperties,
                $targetAction
            )
            ->willReturn($associationMetadata);

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
        self::assertFalse($associationMetadata->isInput());
        self::assertTrue($associationMetadata->isOutput());
    }

    public function testForNestedAssociationWithConfiguredDirection(): void
    {
        $entityClass = 'Test\Class';
        $config = new EntityDefinitionConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';
        $fieldName = 'testField';

        $entityMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata($fieldName);

        $field = $config->addField($fieldName);
        $field->setDataType('nestedAssociation');
        $field->setDirection('output-only');

        $this->objectMetadataFactory->expects(self::once())
            ->method('createObjectMetadata')
            ->with($entityClass, self::identicalTo($config))
            ->willReturn($entityMetadata);
        $this->nestedAssociationMetadataFactory->expects(self::once())
            ->method('createAndAddNestedAssociationMetadata')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $withExcludedProperties,
                $targetAction
            )
            ->willReturn($associationMetadata);

        $result = $this->objectMetadataLoader->loadObjectMetadata(
            $entityClass,
            $config,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($entityMetadata, $result);
        self::assertFalse($associationMetadata->isInput());
        self::assertTrue($associationMetadata->isOutput());
    }
}
