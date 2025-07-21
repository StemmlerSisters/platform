<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\ORM;

use Doctrine\Inflector\Rules\English\InflectorFactory;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\EntityExtendBundle\ORM\RelationMetadataBuilder;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestClass;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestClass2;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendDbIdentifierNameGenerator;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class RelationMetadataBuilderTest extends TestCase
{
    private ConfigManager&MockObject $configManager;
    private ExtendDbIdentifierNameGenerator $nameGenerator;
    private RelationMetadataBuilder $builder;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->nameGenerator = new ExtendDbIdentifierNameGenerator();

        $this->builder = new RelationMetadataBuilder(
            $this->configManager,
            $this->nameGenerator,
            (new InflectorFactory())->build()
        );
    }

    private function getEntityConfig(string $className, array $values = []): Config
    {
        $configId = new EntityConfigId('extend', $className);
        $config = new Config($configId);
        $config->setValues($values);

        return $config;
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupports(Config $extendConfig, bool $expected): void
    {
        self::assertEquals(
            $expected,
            $this->builder->supports($extendConfig)
        );
    }

    public function supportsDataProvider(): array
    {
        return [
            [
                $this->getEntityConfig('Test\Entity'),
                false
            ],
            [
                $this->getEntityConfig('Test\Entity', ['relation' => ['relationKey' => []]]),
                true
            ],
        ];
    }

    /**
     * @dataProvider statesToSkipDataProvider
     */
    public function testShouldSkipBuildForSpecifiedStateOfTargetEntity(string $state): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_ONE;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);

        $targetEntityClass = TestClass2::class;

        $this->configManager->expects(self::atLeastOnce())
            ->method('getEntityConfig')
            ->willReturn($this->getEntityConfig($entityClass, ['state' => $state]));

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => null
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        self::assertFalse($metadataBuilder->getClassMetadata()->hasAssociation($fieldName));
    }

    public function statesToSkipDataProvider(): array
    {
        return [
            [ExtendScope::STATE_NEW],
            [ExtendScope::STATE_DELETE]
        ];
    }

    public function testBuildManyToOne(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_ONE;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);

        $targetEntityClass = TestClass2::class;

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => null
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'             => $entityClass,
                'targetEntity'             => $targetEntityClass,
                'fieldName'                => $fieldName,
                'type'                     => ClassMetadataInfo::MANY_TO_ONE,
                'isOwningSide'             => true,
                'mappedBy'                 => null,
                'inversedBy'               => null,
                'cascade'                  => [],
                'joinColumns'              => [
                    [
                        'name'                 => $fieldName . '_id',
                        'referencedColumnName' => 'id',
                        'nullable'             => true,
                        'unique'               => false,
                        'onDelete'             => 'SET NULL',
                        'columnDefinition'     => null
                    ]
                ],
                'joinColumnFieldNames'     => [
                    $fieldName . '_id' => $fieldName . '_id'
                ],
                'sourceToTargetKeyColumns' => [
                    $fieldName . '_id' => 'id'
                ],
                'targetToSourceKeyColumns' => [
                    'id' => $fieldName . '_id'
                ],
                'fetch'                    => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'          => false,
                'isCascadePersist'         => false,
                'isCascadeRefresh'         => false,
                'isCascadeMerge'           => false,
                'isCascadeDetach'          => false,
                'orphanRemoval'            => false
            ],
            $result
        );
    }

    public function testBuildManyToOneWithAdditionalOptions(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_ONE;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);

        $targetEntityClass = TestClass2::class;

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => null,
                        'cascade'         => ['persist'],
                        'fetch'           => 'extra_lazy',
                        'on_delete'       => 'CASCADE',
                        'nullable'        => false
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'             => $entityClass,
                'targetEntity'             => $targetEntityClass,
                'fieldName'                => $fieldName,
                'type'                     => ClassMetadataInfo::MANY_TO_ONE,
                'isOwningSide'             => true,
                'mappedBy'                 => null,
                'inversedBy'               => null,
                'cascade'                  => ['persist'],
                'joinColumns'              => [
                    [
                        'name'                 => $fieldName . '_id',
                        'referencedColumnName' => 'id',
                        'nullable'             => false,
                        'unique'               => false,
                        'onDelete'             => 'CASCADE',
                        'columnDefinition'     => null
                    ]
                ],
                'joinColumnFieldNames'     => [
                    $fieldName . '_id' => $fieldName . '_id'
                ],
                'sourceToTargetKeyColumns' => [
                    $fieldName . '_id' => 'id'
                ],
                'targetToSourceKeyColumns' => [
                    'id' => $fieldName . '_id'
                ],
                'fetch'                    => ClassMetadataInfo::FETCH_EXTRA_LAZY,
                'isCascadeRemove'          => false,
                'isCascadePersist'         => true,
                'isCascadeRefresh'         => false,
                'isCascadeMerge'           => false,
                'isCascadeDetach'          => false,
                'orphanRemoval'            => false
            ],
            $result
        );
    }

    public function testBuildManyToOneBidirectional(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_ONE;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::ONE_TO_MANY;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);
        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'             => $entityClass,
                'targetEntity'             => $targetEntityClass,
                'fieldName'                => $fieldName,
                'type'                     => ClassMetadataInfo::MANY_TO_ONE,
                'isOwningSide'             => true,
                'mappedBy'                 => null,
                'inversedBy'               => $targetFieldName,
                'cascade'                  => [],
                'joinColumns'              => [
                    [
                        'name'                 => $fieldName . '_id',
                        'referencedColumnName' => 'id',
                        'nullable'             => true,
                        'unique'               => false,
                        'onDelete'             => 'SET NULL',
                        'columnDefinition'     => null
                    ]
                ],
                'joinColumnFieldNames'     => [
                    $fieldName . '_id' => $fieldName . '_id'
                ],
                'sourceToTargetKeyColumns' => [
                    $fieldName . '_id' => 'id'
                ],
                'targetToSourceKeyColumns' => [
                    'id' => $fieldName . '_id'
                ],
                'fetch'                    => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'          => false,
                'isCascadePersist'         => false,
                'isCascadeRefresh'         => false,
                'isCascadeMerge'           => false,
                'isCascadeDetach'          => false,
                'orphanRemoval'            => false
            ],
            $result
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testBuildManyToOneWithCustomizedColumnName(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_ONE;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $columnName = 'src_column_id';
        $fieldConfig = new Config($fieldId, ['column_name' => $columnName]);

        $targetEntityClass = TestClass2::class;

        $this->configManager->expects(self::exactly(2))
            ->method('hasConfig')
            ->willReturnMap([
                [$targetEntityClass, null, false],
                [$entityClass, $fieldName, true]
            ]);
        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $targetEntityConfig = $this->getEntityConfig(
            $targetEntityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => null
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'             => $entityClass,
                'targetEntity'             => $targetEntityClass,
                'fieldName'                => $fieldName,
                'type'                     => ClassMetadataInfo::MANY_TO_ONE,
                'isOwningSide'             => true,
                'mappedBy'                 => null,
                'inversedBy'               => null,
                'cascade'                  => [],
                'joinColumns'              => [
                    [
                        'name'                 => $columnName,
                        'referencedColumnName' => 'id',
                        'nullable'             => true,
                        'unique'               => false,
                        'onDelete'             => 'SET NULL',
                        'columnDefinition'     => null
                    ]
                ],
                'joinColumnFieldNames'     => [
                    $columnName => $columnName
                ],
                'sourceToTargetKeyColumns' => [
                    $columnName => 'id'
                ],
                'targetToSourceKeyColumns' => [
                    'id' => $columnName
                ],
                'fetch'                    => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'          => false,
                'isCascadePersist'         => false,
                'isCascadeRefresh'         => false,
                'isCascadeMerge'           => false,
                'isCascadeDetach'          => false,
                'orphanRemoval'            => false
            ],
            $result
        );
    }

    public function testBuildOneToMany(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::ONE_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $fieldConfig = new Config($fieldId, ['without_default' => true]);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_ONE;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::ONE_TO_MANY,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => false,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'     => $entityClass,
                'targetEntity'     => $targetEntityClass,
                'fieldName'        => $fieldName,
                'type'             => ClassMetadataInfo::ONE_TO_MANY,
                'isOwningSide'     => false,
                'mappedBy'         => $targetFieldName,
                'inversedBy'       => null,
                'cascade'          => [],
                'fetch'            => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'  => false,
                'isCascadePersist' => false,
                'isCascadeRefresh' => false,
                'isCascadeMerge'   => false,
                'isCascadeDetach'  => false,
                'orphanRemoval'    => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }

    public function testBuildOneToManyWithAdditionalCascadeOption(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::ONE_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $fieldConfig = new Config($fieldId, ['without_default' => true]);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_ONE;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::ONE_TO_MANY,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => false,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId,
                        'cascade'         => ['persist', 'detach'],
                        'fetch'           => 'extra_lazy',
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'     => $entityClass,
                'targetEntity'     => $targetEntityClass,
                'fieldName'        => $fieldName,
                'type'             => ClassMetadataInfo::ONE_TO_MANY,
                'isOwningSide'     => false,
                'mappedBy'         => $targetFieldName,
                'inversedBy'       => null,
                'cascade'          => ['persist', 'detach'],
                'fetch'            => ClassMetadataInfo::FETCH_EXTRA_LAZY,
                'isCascadeRemove'  => false,
                'isCascadePersist' => true,
                'isCascadeRefresh' => false,
                'isCascadeMerge'   => false,
                'isCascadeDetach'  => true,
                'orphanRemoval'    => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }

    public function testBuildOneToManyWithAdditionalOrphanRemovalOption(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::ONE_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $fieldConfig = new Config($fieldId, ['without_default' => true]);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_ONE;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::ONE_TO_MANY,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => false,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId,
                        'orphanRemoval'   => true,
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'     => $entityClass,
                'targetEntity'     => $targetEntityClass,
                'fieldName'        => $fieldName,
                'type'             => ClassMetadataInfo::ONE_TO_MANY,
                'isOwningSide'     => false,
                'mappedBy'         => $targetFieldName,
                'inversedBy'       => null,
                'cascade'          => [],
                'fetch'            => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'  => true,
                'isCascadePersist' => false,
                'isCascadeRefresh' => false,
                'isCascadeMerge'   => false,
                'isCascadeDetach'  => false,
                'orphanRemoval'    => true
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testBuildOneToManyWithDefaultRelation(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::ONE_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $fieldConfig = new Config($fieldId, []);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_ONE;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::ONE_TO_MANY,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => false,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'     => $entityClass,
                'targetEntity'     => $targetEntityClass,
                'fieldName'        => $fieldName,
                'type'             => ClassMetadataInfo::ONE_TO_MANY,
                'isOwningSide'     => false,
                'mappedBy'         => $targetFieldName,
                'inversedBy'       => null,
                'cascade'          => [],
                'fetch'            => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'  => false,
                'isCascadePersist' => false,
                'isCascadeRefresh' => false,
                'isCascadeMerge'   => false,
                'isCascadeDetach'  => false,
                'orphanRemoval'    => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertEquals(
            [
                'sourceEntity'             => $entityClass,
                'targetEntity'             => $targetEntityClass,
                'fieldName'                => $defaultRelationFieldName,
                'type'                     => ClassMetadataInfo::MANY_TO_ONE,
                'isOwningSide'             => true,
                'mappedBy'                 => null,
                'inversedBy'               => null,
                'cascade'                  => [],
                'joinColumns'              => [
                    [
                        'name'                 => $defaultRelationFieldName . '_id',
                        'referencedColumnName' => 'id',
                        'nullable'             => true,
                        'unique'               => false,
                        'onDelete'             => 'SET NULL',
                        'columnDefinition'     => null
                    ]
                ],
                'joinColumnFieldNames'     => [
                    $defaultRelationFieldName . '_id' => $defaultRelationFieldName . '_id'
                ],
                'sourceToTargetKeyColumns' => [
                    $defaultRelationFieldName . '_id' => 'id'
                ],
                'targetToSourceKeyColumns' => [
                    'id' => $defaultRelationFieldName . '_id'
                ],
                'fetch'                    => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'          => false,
                'isCascadePersist'         => false,
                'isCascadeRefresh'         => false,
                'isCascadeMerge'           => false,
                'isCascadeDetach'          => false,
                'orphanRemoval'            => false
            ],
            $metadataBuilder->getClassMetadata()->getAssociationMapping($defaultRelationFieldName)
        );
    }

    public function testBuildOneToManyForInheritedRelation(): void
    {
        $entityClass = TestClass::class;
        $fieldName = 'srcField';
        $fieldType = RelationType::ONE_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_ONE;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $this->configManager->expects(self::never())
            ->method('getFieldConfig');

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            'typeInheritedFromManyToOne',
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => false,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'     => $entityClass,
                'targetEntity'     => $targetEntityClass,
                'fieldName'        => $fieldName,
                'type'             => ClassMetadataInfo::ONE_TO_MANY,
                'isOwningSide'     => false,
                'mappedBy'         => $targetFieldName,
                'inversedBy'       => null,
                'cascade'          => [],
                'fetch'            => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'  => false,
                'isCascadePersist' => false,
                'isCascadeRefresh' => false,
                'isCascadeMerge'   => false,
                'isCascadeDetach'  => false,
                'orphanRemoval'    => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testBuildManyToMany(): void
    {
        $entityClass = TestClass::class;

        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $fieldConfig = new Config($fieldId, ['without_default' => true]);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_MANY;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'               => $entityClass,
                'targetEntity'               => $targetEntityClass,
                'fieldName'                  => $fieldName,
                'type'                       => ClassMetadataInfo::MANY_TO_MANY,
                'isOwningSide'               => true,
                'mappedBy'                   => null,
                'inversedBy'                 => $targetFieldName,
                'cascade'                    => [],
                'joinTable'                  => [
                    'name'               => $this->nameGenerator->generateManyToManyJoinTableName(
                        $entityClass,
                        $fieldName,
                        $targetEntityClass
                    ),
                    'joinColumns'        => [
                        [
                            'name'                 => 'testclass_id',
                            'referencedColumnName' => 'id',
                            'onDelete'             => 'CASCADE',
                            'nullable'             => false,
                            'unique'               => false,
                            'columnDefinition'     => null
                        ]
                    ],
                    'inverseJoinColumns' => [
                        [
                            'name'                 => 'testclass2_id',
                            'referencedColumnName' => 'id',
                            'onDelete'             => 'CASCADE',
                            'nullable'             => false,
                            'unique'               => false,
                            'columnDefinition'     => null
                        ]
                    ]
                ],
                'joinTableColumns'           => ['testclass_id', 'testclass2_id'],
                'relationToSourceKeyColumns' => [
                    'testclass_id' => 'id'
                ],
                'relationToTargetKeyColumns' => [
                    'testclass2_id' => 'id'
                ],
                'fetch'                      => ClassMetadataInfo::FETCH_LAZY,
                'isOnDeleteCascade'          => true,
                'isCascadeRemove'            => false,
                'isCascadePersist'           => false,
                'isCascadeRefresh'           => false,
                'isCascadeMerge'             => false,
                'isCascadeDetach'            => false,
                'orphanRemoval'              => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testBuildManyToManyWithAdditionalCascadeOption(): void
    {
        $entityClass = TestClass::class;

        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $fieldConfig = new Config($fieldId, ['without_default' => true]);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_MANY;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId,
                        'cascade'         => ['persist'],
                        'fetch'           => 'extra_lazy',
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'               => $entityClass,
                'targetEntity'               => $targetEntityClass,
                'fieldName'                  => $fieldName,
                'type'                       => ClassMetadataInfo::MANY_TO_MANY,
                'isOwningSide'               => true,
                'mappedBy'                   => null,
                'inversedBy'                 => $targetFieldName,
                'cascade'                    => ['persist'],
                'joinTable'                  => [
                    'name'               => $this->nameGenerator->generateManyToManyJoinTableName(
                        $entityClass,
                        $fieldName,
                        $targetEntityClass
                    ),
                    'joinColumns'        => [
                        [
                            'name'                 => 'testclass_id',
                            'referencedColumnName' => 'id',
                            'onDelete'             => 'CASCADE',
                            'nullable'             => false,
                            'unique'               => false,
                            'columnDefinition'     => null
                        ]
                    ],
                    'inverseJoinColumns' => [
                        [
                            'name'                 => 'testclass2_id',
                            'referencedColumnName' => 'id',
                            'onDelete'             => 'CASCADE',
                            'nullable'             => false,
                            'unique'               => false,
                            'columnDefinition'     => null
                        ]
                    ]
                ],
                'joinTableColumns'           => ['testclass_id', 'testclass2_id'],
                'relationToSourceKeyColumns' => [
                    'testclass_id' => 'id'
                ],
                'relationToTargetKeyColumns' => [
                    'testclass2_id' => 'id'
                ],
                'fetch'                      => ClassMetadataInfo::FETCH_EXTRA_LAZY,
                'isOnDeleteCascade'          => true,
                'isCascadeRemove'            => false,
                'isCascadePersist'           => true,
                'isCascadeRefresh'           => false,
                'isCascadeMerge'             => false,
                'isCascadeDetach'            => false,
                'orphanRemoval'              => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testBuildManyToManyWithDefaultRelation(): void
    {
        $entityClass = TestClass::class;

        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $fieldConfig = new Config($fieldId, []);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_MANY;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'               => $entityClass,
                'targetEntity'               => $targetEntityClass,
                'fieldName'                  => $fieldName,
                'type'                       => ClassMetadataInfo::MANY_TO_MANY,
                'isOwningSide'               => true,
                'mappedBy'                   => null,
                'inversedBy'                 => $targetFieldName,
                'cascade'                    => [],
                'joinTable'                  => [
                    'name'               => $this->nameGenerator->generateManyToManyJoinTableName(
                        $entityClass,
                        $fieldName,
                        $targetEntityClass
                    ),
                    'joinColumns'        => [
                        [
                            'name'                 => 'testclass_id',
                            'referencedColumnName' => 'id',
                            'onDelete'             => 'CASCADE',
                            'nullable'             => false,
                            'unique'               => false,
                            'columnDefinition'     => null
                        ]
                    ],
                    'inverseJoinColumns' => [
                        [
                            'name'                 => 'testclass2_id',
                            'referencedColumnName' => 'id',
                            'onDelete'             => 'CASCADE',
                            'nullable'             => false,
                            'unique'               => false,
                            'columnDefinition'     => null
                        ]
                    ]
                ],
                'joinTableColumns'           => ['testclass_id', 'testclass2_id'],
                'relationToSourceKeyColumns' => [
                    'testclass_id' => 'id'
                ],
                'relationToTargetKeyColumns' => [
                    'testclass2_id' => 'id'
                ],
                'fetch'                      => ClassMetadataInfo::FETCH_LAZY,
                'isOnDeleteCascade'          => true,
                'isCascadeRemove'            => false,
                'isCascadePersist'           => false,
                'isCascadeRefresh'           => false,
                'isCascadeMerge'             => false,
                'isCascadeDetach'            => false,
                'orphanRemoval'              => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertEquals(
            [
                'sourceEntity'             => $entityClass,
                'targetEntity'             => $targetEntityClass,
                'fieldName'                => $defaultRelationFieldName,
                'type'                     => ClassMetadataInfo::MANY_TO_ONE,
                'isOwningSide'             => true,
                'mappedBy'                 => null,
                'inversedBy'               => null,
                'cascade'                  => [],
                'joinColumns'              => [
                    [
                        'name'                 => $defaultRelationFieldName . '_id',
                        'referencedColumnName' => 'id',
                        'nullable'             => true,
                        'unique'               => false,
                        'onDelete'             => 'SET NULL',
                        'columnDefinition'     => null
                    ]
                ],
                'joinColumnFieldNames'     => [
                    $defaultRelationFieldName . '_id' => $defaultRelationFieldName . '_id'
                ],
                'sourceToTargetKeyColumns' => [
                    $defaultRelationFieldName . '_id' => 'id'
                ],
                'targetToSourceKeyColumns' => [
                    'id' => $defaultRelationFieldName . '_id'
                ],
                'fetch'                    => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'          => false,
                'isCascadePersist'         => false,
                'isCascadeRefresh'         => false,
                'isCascadeMerge'           => false,
                'isCascadeDetach'          => false,
                'orphanRemoval'            => false
            ],
            $metadataBuilder->getClassMetadata()->getAssociationMapping($defaultRelationFieldName)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testBuildManyToManyTargetSide(): void
    {
        $entityClass = TestClass::class;

        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_MANY;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => false,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'     => $entityClass,
                'targetEntity'     => $targetEntityClass,
                'fieldName'        => $fieldName,
                'type'             => ClassMetadataInfo::MANY_TO_MANY,
                'isOwningSide'     => false,
                'mappedBy'         => $targetFieldName,
                'inversedBy'       => null,
                'cascade'          => [],
                'joinTable'        => [],
                'fetch'            => ClassMetadataInfo::FETCH_LAZY,
                'isCascadeRemove'  => false,
                'isCascadePersist' => false,
                'isCascadeRefresh' => false,
                'isCascadeMerge'   => false,
                'isCascadeDetach'  => false,
                'orphanRemoval'    => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testBuildManyToManyTargetSideWithAdditionalCascadeOption(): void
    {
        $entityClass = TestClass::class;

        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);

        $targetEntityClass = TestClass2::class;
        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_MANY;
        $targetFieldId = new FieldConfigId('extend', $targetEntityClass, $targetFieldName, $targetFieldType);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $targetEntityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => false,
                        'target_entity'   => $targetEntityClass,
                        'target_field_id' => $targetFieldId,
                        'cascade'         => ['persist'],
                        'fetch'           => 'extra_lazy',
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'     => $entityClass,
                'targetEntity'     => $targetEntityClass,
                'fieldName'        => $fieldName,
                'type'             => ClassMetadataInfo::MANY_TO_MANY,
                'isOwningSide'     => false,
                'mappedBy'         => $targetFieldName,
                'inversedBy'       => null,
                'cascade'          => ['persist'],
                'joinTable'        => [],
                'fetch'            => ClassMetadataInfo::FETCH_EXTRA_LAZY,
                'isCascadeRemove'  => false,
                'isCascadePersist' => true,
                'isCascadeRefresh' => false,
                'isCascadeMerge'   => false,
                'isCascadeDetach'  => false,
                'orphanRemoval'    => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testBuildManyToManyWhenOwningSiteAndInverseSideEntitiesAreEqual(): void
    {
        $entityClass = TestClass::class;

        $fieldName = 'srcField';
        $fieldType = RelationType::MANY_TO_MANY;
        $fieldId = new FieldConfigId('extend', $entityClass, $fieldName, $fieldType);
        $fieldConfig = new Config($fieldId, ['without_default' => true]);

        $targetFieldName = 'targetField';
        $targetFieldType = RelationType::MANY_TO_MANY;
        $targetFieldId = new FieldConfigId('extend', $entityClass, $targetFieldName, $targetFieldType);

        $targetEntityConfig = $this->getEntityConfig(
            $entityClass,
            [
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );

        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->willReturn($targetEntityConfig);

        $this->configManager->expects(self::once())
            ->method('getFieldConfig')
            ->with('extend', $entityClass, $fieldName)
            ->willReturn($fieldConfig);

        $metadataBuilder = new ClassMetadataBuilder(new ClassMetadataInfo($entityClass));
        $relationKey = ExtendHelper::buildRelationKey(
            $entityClass,
            $fieldName,
            RelationType::MANY_TO_ONE,
            $entityClass
        );
        $extendConfig = $this->getEntityConfig(
            $entityClass,
            [
                'relation' => [
                    $relationKey => [
                        'field_id'        => $fieldId,
                        'owner'           => true,
                        'target_entity'   => $entityClass,
                        'target_field_id' => $targetFieldId
                    ]
                ],
                'schema'   => [
                    'relation' => [
                        $fieldName => []
                    ]
                ]
            ]
        );

        $this->builder->build($metadataBuilder, $extendConfig);

        $result = $metadataBuilder->getClassMetadata()->getAssociationMapping($fieldName);
        self::assertEquals(
            [
                'sourceEntity'               => $entityClass,
                'targetEntity'               => $entityClass,
                'fieldName'                  => $fieldName,
                'type'                       => ClassMetadataInfo::MANY_TO_MANY,
                'isOwningSide'               => true,
                'mappedBy'                   => null,
                'inversedBy'                 => $targetFieldName,
                'cascade'                    => [],
                'joinTable'                  => [
                    'name'               => $this->nameGenerator->generateManyToManyJoinTableName(
                        $entityClass,
                        $fieldName,
                        $entityClass
                    ),
                    'joinColumns'        => [
                        [
                            'name'                 => 'src_testclass_id',
                            'referencedColumnName' => 'id',
                            'onDelete'             => 'CASCADE',
                            'nullable'             => false,
                            'unique'               => false,
                            'columnDefinition'     => null
                        ]
                    ],
                    'inverseJoinColumns' => [
                        [
                            'name'                 => 'dest_testclass_id',
                            'referencedColumnName' => 'id',
                            'onDelete'             => 'CASCADE',
                            'nullable'             => false,
                            'unique'               => false,
                            'columnDefinition'     => null
                        ]
                    ]
                ],
                'joinTableColumns'           => ['src_testclass_id', 'dest_testclass_id'],
                'relationToSourceKeyColumns' => [
                    'src_testclass_id' => 'id'
                ],
                'relationToTargetKeyColumns' => [
                    'dest_testclass_id' => 'id'
                ],
                'fetch'                      => ClassMetadataInfo::FETCH_LAZY,
                'isOnDeleteCascade'          => true,
                'isCascadeRemove'            => false,
                'isCascadePersist'           => false,
                'isCascadeRefresh'           => false,
                'isCascadeMerge'             => false,
                'isCascadeDetach'            => false,
                'orphanRemoval'              => false
            ],
            $result
        );

        $defaultRelationFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
        self::assertFalse(
            $metadataBuilder->getClassMetadata()->hasAssociation($defaultRelationFieldName)
        );
    }
}
