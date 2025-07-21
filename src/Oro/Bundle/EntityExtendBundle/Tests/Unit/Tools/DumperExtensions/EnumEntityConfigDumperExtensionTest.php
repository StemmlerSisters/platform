<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Tools\DumperExtensions;

use Oro\Bundle\EntityBundle\EntityConfig\DatagridScope;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigModel;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\ExtendEntityConfigProviderInterface;
use Oro\Bundle\EntityExtendBundle\Configuration\EntityExtendConfigurationProvider;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Entity\EnumOption;
use Oro\Bundle\EntityExtendBundle\Entity\Repository\EnumOptionRepository;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\FieldTypeHelper;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\OutdatedExtendExtension;
use Oro\Bundle\EntityExtendBundle\Tools\DumperExtensions\EnumEntityConfigDumperExtension;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendDbIdentifierNameGenerator;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\EntityExtendBundle\Tools\RelationBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnumEntityConfigDumperExtensionTest extends TestCase
{
    private ConfigManager&MockObject $configManager;
    private RelationBuilder&MockObject $relationBuilder;
    private ExtendDbIdentifierNameGenerator $nameGenerator;
    private ExtendEntityConfigProviderInterface&MockObject $extendEntityConfigProvider;
    private EnumEntityConfigDumperExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->relationBuilder = $this->createMock(RelationBuilder::class);
        $this->nameGenerator = new ExtendDbIdentifierNameGenerator();
        $this->extendEntityConfigProvider = $this->createMock(ExtendEntityConfigProviderInterface::class);

        $entityExtendConfigurationProvider = $this->createMock(EntityExtendConfigurationProvider::class);
        $entityExtendConfigurationProvider->expects(self::any())
            ->method('getUnderlyingTypes')
            ->willReturn(['enum' => 'manyToOne', 'multiEnum' => 'manyToMany']);

        $this->extension = new EnumEntityConfigDumperExtension(
            $this->configManager,
            $this->relationBuilder,
            new FieldTypeHelper($entityExtendConfigurationProvider),
            $this->nameGenerator,
            $this->extendEntityConfigProvider
        );
    }

    public function testSupportsPreUpdate(): void
    {
        $this->assertTrue(
            $this->extension->supports(ExtendConfigDumper::ACTION_PRE_UPDATE)
        );
    }

    public function testSupportsPostUpdate(): void
    {
        $this->assertTrue(
            $this->extension->supports(ExtendConfigDumper::ACTION_POST_UPDATE)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testPreUpdate(): void
    {
        $entityConfig1 = new Config(new EntityConfigId('extend', 'Test\EnumValue1'));
        $entityConfig1->set('is_extend', true);
        $entityConfig2 = new Config(new EntityConfigId('extend', 'Test\EnumValue2'));

        $fieldConfig1 = new Config(new FieldConfigId('extend', 'Test\EnumValue1', 'field1', 'enum'));
        $fieldConfig1->set('state', ExtendScope::STATE_NEW);
        $fieldConfig2 = new Config(new FieldConfigId('extend', 'Test\EnumValue1', 'field2', 'multiEnum'));
        $fieldConfig2->set('state', ExtendScope::STATE_UPDATE);
        $fieldConfig3 = new Config(new FieldConfigId('extend', 'Test\EnumValue1', 'field3', 'enum'));
        $fieldConfig3->set('state', ExtendScope::STATE_DELETE);
        $fieldConfig4 = new Config(new FieldConfigId('extend', 'Test\EnumValue1', 'field4', 'manyToOne'));
        $fieldConfig4->set('state', ExtendScope::STATE_NEW);
        $fieldConfig5 = new Config(new FieldConfigId('extend', 'Test\EnumValue1', 'field5', 'enum'));
        $fieldConfig5->set('state', ExtendScope::STATE_NEW);

        $enumFieldConfig1 = new Config(new FieldConfigId('enum', 'Test\EnumValue1', 'field1', 'enum'));
        $enumFieldConfig1->set('enum_name', 'Test Enum 1');
        $enumFieldConfig1->set('enum_public', true);
        $enumFieldConfig2 = new Config(new FieldConfigId('enum', 'Test\EnumValue1', 'field2', 'enum'));
        $enumFieldConfig2->set('enum_name', 'Test Enum 2');
        $enumFieldConfig2->set('enum_public', true);
        $enumFieldConfig5 = new Config(new FieldConfigId('enum', 'Test\EnumValue1', 'field5', 'enum'));

        $entityConfigs = [$entityConfig1, $entityConfig2];
        $fieldConfigs = [$fieldConfig1, $fieldConfig2, $fieldConfig3, $fieldConfig4, $fieldConfig5];

        $enumCode1 = ExtendHelper::buildEnumCode('Test Enum 1');
        $enumCode2 = ExtendHelper::buildEnumCode('Test Enum 2');
        $enumCode5 = ExtendHelper::generateEnumCode('Test\EnumValue1', 'field5');
        $enumOptionClassName = EnumOption::class;

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $enumConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->any())
            ->method('getProvider')
            ->willReturnMap([
                ['extend', $extendConfigProvider],
                ['enum', $enumConfigProvider],
            ]);
        $this->extendEntityConfigProvider->expects($this->once())
            ->method('getExtendEntityConfigs')
            ->willReturn($entityConfigs);
        $extendConfigProvider->expects($this->exactly(2))
            ->method('getConfigs')
            ->willReturnMap([
                [$entityConfig1->getId()->getClassName(), false, $fieldConfigs],
                [$entityConfig2->getId()->getClassName(), false, []]
            ]);
        $enumConfigProvider->expects($this->exactly(3))
            ->method('getConfig')
            ->willReturnMap([
                [$entityConfig1->getId()->getClassName(), 'field1', $enumFieldConfig1],
                [$entityConfig1->getId()->getClassName(), 'field2', $enumFieldConfig2],
                [$entityConfig1->getId()->getClassName(), 'field5', $enumFieldConfig5]
            ]);

        $this->configManager->expects($this->exactly(3))
            ->method('hasConfigEntityModel')
            ->willReturnMap([
                [$enumOptionClassName, false],
                [$enumOptionClassName, true],
                [$enumOptionClassName, true],
            ]);

        $this->configManager->expects($this->any())
            ->method('createConfigEntityModel')
            ->with($enumOptionClassName, ConfigModel::MODE_HIDDEN);

        $this->setAddEnumValueEntityFieldsExpectations($enumOptionClassName, $enumCode1);

        $this->relationBuilder->expects($this->exactly(3))
            ->method('updateEntityConfigs')
            ->withConsecutive(
                [
                    $enumOptionClassName,
                    [
                        'entity' => [
                            'label'        => ExtendHelper::getEnumTranslationKey('label', $enumCode1),
                            'plural_label' => ExtendHelper::getEnumTranslationKey('plural_label', $enumCode1),
                            'description'  => ExtendHelper::getEnumTranslationKey('description', $enumCode1)
                        ],
                        'extend' => [
                            'owner'     => ExtendScope::OWNER_SYSTEM,
                            'is_extend' => true,
                            'table' => OutdatedExtendExtension::generateEnumTableName($enumCode1, true),
                            'inherit' => AbstractEnumValue::class
                        ],
                        'enum'   => [
                            'code'     => $enumCode1,
                            'public'   => true,
                            'multiple' => false
                        ]
                    ]
                ],
                [
                    $enumOptionClassName,
                    [
                        'entity' => [
                            'label'        => ExtendHelper::getEnumTranslationKey('label', $enumCode2),
                            'plural_label' => ExtendHelper::getEnumTranslationKey('plural_label', $enumCode2),
                            'description'  => ExtendHelper::getEnumTranslationKey('description', $enumCode2)
                        ],
                        'extend' => [
                            'owner'     => ExtendScope::OWNER_SYSTEM,
                            'is_extend' => true,
                            'table' => OutdatedExtendExtension::generateEnumTableName($enumCode2, true),
                            'inherit' => AbstractEnumValue::class
                        ],
                        'enum' => [
                            'code'   => $enumCode2,
                            'public' => true,
                            'multiple' => true
                        ]
                    ]
                ],
                [
                    $enumOptionClassName,
                    [
                        'entity' => [
                            'label'        => ExtendHelper::getEnumTranslationKey('label', $enumCode5),
                            'plural_label' => ExtendHelper::getEnumTranslationKey('plural_label', $enumCode5),
                            'description'  => ExtendHelper::getEnumTranslationKey('description', $enumCode5)
                        ],
                        'extend' => [
                            'owner'     => ExtendScope::OWNER_SYSTEM,
                            'is_extend' => true,
                            'table' => OutdatedExtendExtension::generateEnumTableName($enumCode5, true),
                            'inherit' => AbstractEnumValue::class
                        ],
                        'enum' => [
                            'code'   => $enumCode5,
                            'public' => false,
                            'multiple' => false
                        ]
                    ]
                ]
            );

        $this->relationBuilder->expects($this->exactly(2))
            ->method('addManyToOneRelation')
            ->withConsecutive(
                [
                    $this->identicalTo($entityConfig1),
                    $enumOptionClassName,
                    'field1',
                    'name',
                    [
                        'enum' => [
                            'enum_code' => $enumCode1
                        ]
                    ],
                    'enum'
                ],
                [
                    $this->identicalTo($entityConfig1),
                    $enumOptionClassName,
                    'field5',
                    'name',
                    [
                        'enum' => [
                            'enum_code' => $enumCode5
                        ]
                    ],
                    'enum'
                ]
            );
        $this->relationBuilder->expects($this->once())
            ->method('addManyToManyRelation')
            ->with(
                $this->identicalTo($entityConfig1),
                $enumOptionClassName,
                'field2',
                ['name'],
                ['name'],
                ['name'],
                [
                    'enum'   => [
                        'enum_code' => $enumCode2
                    ],
                    'extend' => [
                        'without_default' => true
                    ]
                ],
                'multiEnum'
            );

        $this->extension->preUpdate();
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testPreUpdateForNewEnumWithLongEnumCode(): void
    {
        $entityConfig1 = new Config(new EntityConfigId('extend', 'Test\EnumValue1'));
        $entityConfig1->set('is_extend', true);

        $fieldConfig1 = new Config(new FieldConfigId('extend', 'Test\EnumValue1', 'field1', 'enum'));
        $fieldConfig1->set('state', ExtendScope::STATE_NEW);

        $enumFieldConfig1 = new Config(new FieldConfigId('enum', 'Test\EnumValue1', 'field1', 'enum'));

        $entityConfigs = [$entityConfig1];
        $fieldConfigs = [$fieldConfig1];

        $enumCode1 = ExtendHelper::generateEnumCode('Test\EnumValue1', 'field1');
        $enumValueClassName1 = EnumOption::class;

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $enumConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->any())
            ->method('getProvider')
            ->willReturnMap([
                ['extend', $extendConfigProvider],
                ['enum', $enumConfigProvider],
            ]);
        $this->extendEntityConfigProvider->expects($this->once())
            ->method('getExtendEntityConfigs')
            ->willReturn($entityConfigs);
        $extendConfigProvider->expects($this->once())
            ->method('getConfigs')
            ->with($entityConfig1->getId()->getClassName())
            ->willReturn($fieldConfigs);
        $enumConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($entityConfig1->getId()->getClassName(), 'field1')
            ->willReturn($enumFieldConfig1);

        $this->configManager->expects($this->once())
            ->method('hasConfigEntityModel')
            ->willReturnMap([
                [$enumValueClassName1, false],
            ]);

        $this->configManager->expects($this->once())
            ->method('createConfigEntityModel')
            ->with($enumValueClassName1, ConfigModel::MODE_HIDDEN);

        $this->setAddEnumValueEntityFieldsExpectations($enumValueClassName1, $enumCode1);

        $this->relationBuilder->expects($this->once())
            ->method('updateEntityConfigs')
            ->with(
                $enumValueClassName1,
                [
                    'entity' => [
                        'label'        => ExtendHelper::getEnumTranslationKey('label', $enumCode1),
                        'plural_label' => ExtendHelper::getEnumTranslationKey('plural_label', $enumCode1),
                        'description'  => ExtendHelper::getEnumTranslationKey('description', $enumCode1)
                    ],
                    'extend' => [
                        'owner'     => ExtendScope::OWNER_SYSTEM,
                        'is_extend' => true,
                        'table' => OutdatedExtendExtension::generateEnumTableName($enumCode1, true),
                        'inherit' => AbstractEnumValue::class
                    ],
                    'enum'   => [
                        'code'     => $enumCode1,
                        'public'   => false,
                        'multiple' => false
                    ]
                ]
            );
        $this->relationBuilder->expects($this->once())
            ->method('addManyToOneRelation')
            ->with(
                $this->identicalTo($entityConfig1),
                $enumValueClassName1,
                'field1',
                'name',
                [
                    'enum' => [
                        'enum_code' => $enumCode1
                    ]
                ],
                'enum'
            );

        $this->extension->preUpdate();
    }

    private function setAddEnumValueEntityFieldsExpectations(string $enumValueClassName, string $enumCode): void
    {
        $this->configManager->expects($this->any())
            ->method('createConfigFieldModel')
            ->withConsecutive(
                [$enumValueClassName, 'id', 'string'],
                [$enumValueClassName, 'name', 'string'],
                [$enumValueClassName, 'priority', 'integer'],
                [$enumValueClassName, 'default', 'boolean']
            );

        $this->relationBuilder->expects($this->any())
            ->method('updateFieldConfigs')
            ->withConsecutive(
                [
                    $enumValueClassName,
                    'id',
                    [
                        'entity'       => [
                            'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'id'),
                            'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'id')
                        ],
                        'importexport' => [
                            'identity' => false,
                        ],
                    ]
                ],
                [
                    $enumValueClassName,
                    'name',
                    [
                        'entity'       => [
                            'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'name'),
                            'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'name')
                        ],
                        'datagrid'     => [
                            'is_visible' => DatagridScope::IS_VISIBLE_FALSE
                        ],
                        'importexport' => [
                            'identity' => true,
                        ],
                    ]
                ],
                [
                    $enumValueClassName,
                    'priority',
                    [
                        'entity'   => [
                            'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'priority'),
                            'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'priority')
                        ],
                        'datagrid' => [
                            'is_visible' => DatagridScope::IS_VISIBLE_FALSE
                        ]
                    ]
                ],
                [
                    $enumValueClassName,
                    'default',
                    [
                        'entity'   => [
                            'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'default'),
                            'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'default')
                        ],
                        'datagrid' => [
                            'is_visible' => DatagridScope::IS_VISIBLE_FALSE
                        ]
                    ]
                ]
            );
    }

    public function testPostUpdateForEnumValues(): void
    {
        $entityConfig1 = new Config(new EntityConfigId('extend', 'Test\EnumValue1'));
        $entityConfig1->set('inherit', AbstractEnumValue::class);
        $entityConfig2 = new Config(new EntityConfigId('extend', 'Test\EnumValue2'));
        $entityConfig2->set('inherit', AbstractEnumValue::class);
        $entityConfig2->set(
            'schema',
            [
                'doctrine' => [
                    'Test\EnumValue2' => [
                        'repositoryClass' => EnumOptionRepository::class
                    ]
                ]
            ]
        );

        $entityConfigs = [$entityConfig1, $entityConfig2];

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('extend')
            ->willReturn($extendConfigProvider);
        $this->extendEntityConfigProvider->expects($this->once())
            ->method('getExtendEntityConfigs')
            ->willReturn($entityConfigs);

        $this->configManager->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($entityConfig1));

        $this->extension->postUpdate();

        $this->assertEquals(
            [
                'doctrine' => [
                    'Test\EnumValue1' => [
                        'repositoryClass' => EnumOptionRepository::class
                    ]
                ]
            ],
            $entityConfig1->get('schema')
        );
    }

    public function testPostUpdateForMultiEnumFieldsInCustomEntity(): void
    {
        $entityConfig1 = new Config(new EntityConfigId('extend', 'Extend\EnumValue1'));
        $entityConfig1->set('owner', ExtendScope::OWNER_CUSTOM);
        $entityConfig1->set('is_extend', true);
        $entityConfig1->set(
            'schema',
            [
                'doctrine' => [
                    'Extend\EnumValue1' => [
                        'fields' => [
                            ExtendHelper::getMultiEnumSnapshotFieldName('field2') => [
                                'column' => 'field2'
                            ]
                        ]
                    ]
                ]
            ]
        );
        $entityConfig2 = new Config(new EntityConfigId('extend', 'Extend\EnumValue2'));

        $fieldConfig1 = new Config(new FieldConfigId('extend', 'Extend\EnumValue1', 'field1', 'multiEnum'));
        $fieldConfig2 = new Config(new FieldConfigId('extend', 'Extend\EnumValue1', 'field2', 'multiEnum'));
        $fieldConfig3 = new Config(new FieldConfigId('extend', 'Extend\EnumValue1', 'field3', 'enum'));

        $entityConfigs = [$entityConfig1, $entityConfig2];
        $fieldConfigs = [$fieldConfig1, $fieldConfig2, $fieldConfig3];

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('extend')
            ->willReturn($extendConfigProvider);
        $this->extendEntityConfigProvider->expects($this->once())
            ->method('getExtendEntityConfigs')
            ->willReturn($entityConfigs);
        $extendConfigProvider->expects($this->once())
            ->method('getConfigs')
            ->with($entityConfig1->getId()->getClassName())
            ->willReturn($fieldConfigs);

        $this->configManager->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($entityConfig1));

        $this->extension->postUpdate();

        $this->assertEquals(
            [
                'doctrine' => [
                    'Extend\EnumValue1' => [
                        'fields' => [
                            ExtendHelper::getMultiEnumSnapshotFieldName('field1') => [
                                'column'   => $this->nameGenerator->generateMultiEnumSnapshotColumnName('field1'),
                                'type'     => 'string',
                                'nullable' => true,
                                'length'   => ExtendHelper::MAX_ENUM_SNAPSHOT_LENGTH,
                            ],
                            ExtendHelper::getMultiEnumSnapshotFieldName('field2') => [
                                'column' => 'field2'
                            ]
                        ]
                    ]
                ],
                'property' => [
                    ExtendHelper::getMultiEnumSnapshotFieldName('field1') => []
                ]
            ],
            $entityConfig1->get('schema')
        );
    }

    public function testPostUpdateForDeletedMultiEnumField(): void
    {
        $entityConfig = new Config(new EntityConfigId('extend', 'Extend\EnumValue1'));
        $entityConfig->set('owner', ExtendScope::OWNER_CUSTOM);
        $entityConfig->set('is_extend', true);
        $entityConfig->set(
            'schema',
            [
                'doctrine' => [
                    'Extend\EnumValue1' => [
                        'fields' => [
                            ExtendHelper::getMultiEnumSnapshotFieldName('field2') => [
                                'column' => 'field2'
                            ]
                        ]
                    ]
                ]
            ]
        );

        $fieldConfig = new Config(new FieldConfigId('extend', 'Extend\EnumValue1', 'field1', 'multiEnum'));
        $fieldConfig->set('is_deleted', true);

        $entityConfigs = [$entityConfig];
        $fieldConfigs = [$fieldConfig];

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('extend')
            ->willReturn($extendConfigProvider);
        $this->extendEntityConfigProvider->expects($this->once())
            ->method('getExtendEntityConfigs')
            ->willReturn($entityConfigs);
        $extendConfigProvider->expects($this->once())
            ->method('getConfigs')
            ->with($entityConfig->getId()->getClassName())
            ->willReturn($fieldConfigs);

        $this->configManager->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($entityConfig));

        $this->extension->postUpdate();

        $this->assertEquals(
            [
                'doctrine' => [
                    'Extend\EnumValue1' => [
                        'fields' => [
                            ExtendHelper::getMultiEnumSnapshotFieldName('field1') => [
                                'column'   => $this->nameGenerator->generateMultiEnumSnapshotColumnName('field1'),
                                'type'     => 'string',
                                'nullable' => true,
                                'length'   => ExtendHelper::MAX_ENUM_SNAPSHOT_LENGTH,
                            ],
                            ExtendHelper::getMultiEnumSnapshotFieldName('field2') => [
                                'column' => 'field2'
                            ]
                        ]
                    ]
                ],
                'property' => [
                    ExtendHelper::getMultiEnumSnapshotFieldName('field1') => [
                        'private' => true
                    ]
                ]
            ],
            $entityConfig->get('schema')
        );
    }
}
