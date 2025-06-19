<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetMetadata\Loader;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionFieldConfig;
use Oro\Bundle\ApiBundle\Metadata\AssociationMetadata;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Metadata\FieldMetadata;
use Oro\Bundle\ApiBundle\Metadata\MetaPropertyMetadata;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\Loader\EntityMetadataFactory;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\Loader\EntityNestedAssociationMetadataFactory;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\Loader\NestedAssociationMetadataHelper;
use PHPUnit\Framework\MockObject\MockObject;

class EntityNestedAssociationMetadataFactoryTest extends LoaderTestCase
{
    private NestedAssociationMetadataHelper&MockObject $nestedAssociationMetadataHelper;
    private EntityMetadataFactory&MockObject $entityMetadataFactory;
    private EntityNestedAssociationMetadataFactory $entityNestedAssociationMetadataFactory;

    #[\Override]
    protected function setUp(): void
    {
        $this->nestedAssociationMetadataHelper = $this->createMock(NestedAssociationMetadataHelper::class);
        $this->entityMetadataFactory = $this->createMock(EntityMetadataFactory::class);

        $this->entityNestedAssociationMetadataFactory = new EntityNestedAssociationMetadataFactory(
            $this->nestedAssociationMetadataHelper,
            $this->entityMetadataFactory
        );
    }

    public function testCreateAndAddNestedAssociationMetadataWhenNoAssociationDataType(): void
    {
        $entityMetadata = new EntityMetadata('Test\Entity');
        $entityClass = 'Test\Class';
        $fieldName = 'testField';
        $field = new EntityDefinitionFieldConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $classMetadata = $this->getClassMetadataMock();

        $targetConfig = $field->createAndSetTargetEntity();
        $idFieldName = 'id';
        $idField = $targetConfig->addField($idFieldName);

        $associationMetadata = new AssociationMetadata();
        $associationTargetMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);

        $idFieldMetadata = new FieldMetadata();

        $classMetadata->expects(self::once())
            ->method('hasField')
            ->with($idFieldName)
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getTypeOfField')
            ->with($idFieldName)
            ->willReturn('integer');

        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('addNestedAssociation')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($associationMetadata);
        $this->entityMetadataFactory->expects(self::once())
            ->method('createAndAddFieldMetadata')
            ->with(
                self::identicalTo($associationTargetMetadata),
                self::identicalTo($classMetadata),
                $idFieldName,
                self::identicalTo($idField),
                $targetAction
            )
            ->willReturn($idFieldMetadata);
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('setTargetPropertyPath')
            ->with(
                self::identicalTo($idFieldMetadata),
                $idFieldName,
                self::identicalTo($idField),
                $targetAction
            );
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('getIdentifierFieldName')
            ->willReturn($idFieldName);

        $result = $this->entityNestedAssociationMetadataFactory->createAndAddNestedAssociationMetadata(
            $entityMetadata,
            $classMetadata,
            $entityClass,
            $fieldName,
            $field,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($associationMetadata, $result);
        self::assertEquals('integer', $associationMetadata->getDataType());
    }

    public function testCreateAndAddNestedAssociationMetadataWhenNoAssociationDataTypeAndIfFieldHasPropertyPath(): void
    {
        $entityMetadata = new EntityMetadata('Test\Entity');
        $entityClass = 'Test\Class';
        $fieldName = 'testField';
        $field = new EntityDefinitionFieldConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $classMetadata = $this->getClassMetadataMock();

        $targetConfig = $field->createAndSetTargetEntity();
        $idFieldName = 'id';
        $idFieldPropertyPath = 'idPropertyPath';
        $idField = $targetConfig->addField($idFieldName);
        $idField->setPropertyPath($idFieldPropertyPath);

        $associationMetadata = new AssociationMetadata();
        $associationTargetMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);

        $idFieldMetadata = new FieldMetadata();

        $classMetadata->expects(self::once())
            ->method('hasField')
            ->with($idFieldPropertyPath)
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getTypeOfField')
            ->with($idFieldPropertyPath)
            ->willReturn('integer');

        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('addNestedAssociation')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($associationMetadata);
        $this->entityMetadataFactory->expects(self::once())
            ->method('createAndAddFieldMetadata')
            ->with(
                self::identicalTo($associationTargetMetadata),
                self::identicalTo($classMetadata),
                $idFieldName,
                self::identicalTo($idField),
                $targetAction
            )
            ->willReturn($idFieldMetadata);
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('setTargetPropertyPath')
            ->with(
                self::identicalTo($idFieldMetadata),
                $idFieldName,
                self::identicalTo($idField),
                $targetAction
            );
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('getIdentifierFieldName')
            ->willReturn($idFieldName);

        $result = $this->entityNestedAssociationMetadataFactory->createAndAddNestedAssociationMetadata(
            $entityMetadata,
            $classMetadata,
            $entityClass,
            $fieldName,
            $field,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($associationMetadata, $result);
        self::assertEquals('integer', $associationMetadata->getDataType());
    }

    public function testCreateAndAddNestedAssociationMetadataWhenNoAssociationDataTypeAndNoManageableFieldForId(): void
    {
        $entityMetadata = new EntityMetadata('Test\Entity');
        $entityClass = 'Test\Class';
        $fieldName = 'testField';
        $field = new EntityDefinitionFieldConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $classMetadata = $this->getClassMetadataMock();

        $targetConfig = $field->createAndSetTargetEntity();
        $idFieldName = 'id';
        $idField = $targetConfig->addField($idFieldName);

        $associationMetadata = new AssociationMetadata();
        $associationTargetMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);

        $idFieldMetadata = new FieldMetadata();

        $classMetadata->expects(self::once())
            ->method('hasField')
            ->with($idFieldName)
            ->willReturn(false);
        $classMetadata->expects(self::never())
            ->method('getTypeOfField');

        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('addNestedAssociation')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($associationMetadata);
        $this->entityMetadataFactory->expects(self::once())
            ->method('createAndAddFieldMetadata')
            ->with(
                self::identicalTo($associationTargetMetadata),
                self::identicalTo($classMetadata),
                $idFieldName,
                self::identicalTo($idField),
                $targetAction
            )
            ->willReturn($idFieldMetadata);
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('setTargetPropertyPath')
            ->with(
                self::identicalTo($idFieldMetadata),
                $idFieldName,
                self::identicalTo($idField),
                $targetAction
            );
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('getIdentifierFieldName')
            ->willReturn($idFieldName);

        $result = $this->entityNestedAssociationMetadataFactory->createAndAddNestedAssociationMetadata(
            $entityMetadata,
            $classMetadata,
            $entityClass,
            $fieldName,
            $field,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($associationMetadata, $result);
        self::assertNull($associationMetadata->getDataType());
    }

    public function testCreateAndAddNestedAssociationMetadataForExcludedTargetField(): void
    {
        $entityMetadata = new EntityMetadata('Test\Entity');
        $entityClass = 'Test\Class';
        $fieldName = 'testField';
        $field = new EntityDefinitionFieldConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $classMetadata = $this->getClassMetadataMock();

        $targetConfig = $field->createAndSetTargetEntity();
        $targetFieldName = 'targetField';
        $targetField = $targetConfig->addField($targetFieldName);
        $targetField->setExcluded();

        $associationMetadata = new AssociationMetadata();
        $associationMetadata->setDataType('integer');
        $associationTargetMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);

        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('addNestedAssociation')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($associationMetadata);
        $this->entityMetadataFactory->expects(self::never())
            ->method('createAndAddFieldMetadata');
        $this->nestedAssociationMetadataHelper->expects(self::never())
            ->method('setTargetPropertyPath');
        $this->nestedAssociationMetadataHelper->expects(self::never())
            ->method('getIdentifierFieldName');

        $result = $this->entityNestedAssociationMetadataFactory->createAndAddNestedAssociationMetadata(
            $entityMetadata,
            $classMetadata,
            $entityClass,
            $fieldName,
            $field,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($associationMetadata, $result);
    }

    public function testCreateAndAddNestedAssociationMetadataForExcludedTargetFieldWithExcludedProperties(): void
    {
        $entityMetadata = new EntityMetadata('Test\Entity');
        $entityClass = 'Test\Class';
        $fieldName = 'testField';
        $field = new EntityDefinitionFieldConfig();
        $withExcludedProperties = true;
        $targetAction = 'testAction';

        $classMetadata = $this->getClassMetadataMock();

        $targetConfig = $field->createAndSetTargetEntity();
        $targetFieldName = 'targetField';
        $targetField = $targetConfig->addField($targetFieldName);
        $targetField->setExcluded();

        $associationMetadata = new AssociationMetadata();
        $associationMetadata->setDataType('integer');
        $associationTargetMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $targetPropertyMetadata = new FieldMetadata();

        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('addNestedAssociation')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($associationMetadata);
        $this->entityMetadataFactory->expects(self::once())
            ->method('createAndAddFieldMetadata')
            ->with(
                self::identicalTo($associationTargetMetadata),
                self::identicalTo($classMetadata),
                $targetFieldName,
                self::identicalTo($targetField),
                $targetAction
            )
            ->willReturn($targetPropertyMetadata);
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('setTargetPropertyPath')
            ->with(
                self::identicalTo($targetPropertyMetadata),
                $targetFieldName,
                self::identicalTo($targetField),
                $targetAction
            );
        $this->nestedAssociationMetadataHelper->expects(self::never())
            ->method('getIdentifierFieldName');

        $result = $this->entityNestedAssociationMetadataFactory->createAndAddNestedAssociationMetadata(
            $entityMetadata,
            $classMetadata,
            $entityClass,
            $fieldName,
            $field,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($associationMetadata, $result);
    }

    public function testCreateAndAddNestedAssociationMetadataForField(): void
    {
        $entityMetadata = new EntityMetadata('Test\Entity');
        $entityClass = 'Test\Class';
        $fieldName = 'testField';
        $field = new EntityDefinitionFieldConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $classMetadata = $this->getClassMetadataMock();

        $targetConfig = $field->createAndSetTargetEntity();
        $targetFieldName = 'targetField';
        $targetField = $targetConfig->addField($targetFieldName);

        $associationMetadata = new AssociationMetadata();
        $associationMetadata->setDataType('integer');
        $associationTargetMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $targetPropertyMetadata = new FieldMetadata();

        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('addNestedAssociation')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($associationMetadata);
        $this->entityMetadataFactory->expects(self::once())
            ->method('createAndAddFieldMetadata')
            ->with(
                self::identicalTo($associationTargetMetadata),
                self::identicalTo($classMetadata),
                $targetFieldName,
                self::identicalTo($targetField),
                $targetAction
            )
            ->willReturn($targetPropertyMetadata);
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('setTargetPropertyPath')
            ->with(
                self::identicalTo($targetPropertyMetadata),
                $targetFieldName,
                self::identicalTo($targetField),
                $targetAction
            );
        $this->nestedAssociationMetadataHelper->expects(self::never())
            ->method('getIdentifierFieldName');

        $result = $this->entityNestedAssociationMetadataFactory->createAndAddNestedAssociationMetadata(
            $entityMetadata,
            $classMetadata,
            $entityClass,
            $fieldName,
            $field,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($associationMetadata, $result);
    }

    public function testCreateAndAddNestedAssociationMetadataForMetaProperty(): void
    {
        $entityMetadata = new EntityMetadata('Test\Entity');
        $entityClass = 'Test\Class';
        $fieldName = 'testField';
        $field = new EntityDefinitionFieldConfig();
        $withExcludedProperties = false;
        $targetAction = 'testAction';

        $classMetadata = $this->getClassMetadataMock();

        $targetConfig = $field->createAndSetTargetEntity();
        $targetFieldName = 'targetField';
        $targetField = $targetConfig->addField($targetFieldName);
        $targetField->setMetaProperty(true);

        $associationMetadata = new AssociationMetadata();
        $associationMetadata->setDataType('integer');
        $associationTargetMetadata = new EntityMetadata('Test\Entity');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $targetPropertyMetadata = new MetaPropertyMetadata();

        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('addNestedAssociation')
            ->with(
                self::identicalTo($entityMetadata),
                $entityClass,
                $fieldName,
                self::identicalTo($field),
                $targetAction
            )
            ->willReturn($associationMetadata);
        $this->entityMetadataFactory->expects(self::once())
            ->method('createAndAddMetaPropertyMetadata')
            ->with(
                self::identicalTo($associationTargetMetadata),
                self::identicalTo($classMetadata),
                $targetFieldName,
                self::identicalTo($targetField),
                $targetAction
            )
            ->willReturn($targetPropertyMetadata);
        $this->nestedAssociationMetadataHelper->expects(self::once())
            ->method('setTargetPropertyPath')
            ->with(
                self::identicalTo($targetPropertyMetadata),
                $targetFieldName,
                self::identicalTo($targetField),
                $targetAction
            );
        $this->nestedAssociationMetadataHelper->expects(self::never())
            ->method('getIdentifierFieldName');

        $result = $this->entityNestedAssociationMetadataFactory->createAndAddNestedAssociationMetadata(
            $entityMetadata,
            $classMetadata,
            $entityClass,
            $fieldName,
            $field,
            $withExcludedProperties,
            $targetAction
        );
        self::assertSame($associationMetadata, $result);
    }
}
