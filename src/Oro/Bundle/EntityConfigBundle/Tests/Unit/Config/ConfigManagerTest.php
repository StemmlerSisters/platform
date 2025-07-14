<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Config;

use Oro\Bundle\EntityConfigBundle\Audit\AuditManager;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigCache;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\ConfigModelManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Event\EntityConfigEvent;
use Oro\Bundle\EntityConfigBundle\Event\Events;
use Oro\Bundle\EntityConfigBundle\Event\FieldConfigEvent;
use Oro\Bundle\EntityConfigBundle\Exception\LogicException;
use Oro\Bundle\EntityConfigBundle\Exception\RuntimeException;
use Oro\Bundle\EntityConfigBundle\Metadata\EntityMetadata;
use Oro\Bundle\EntityConfigBundle\Metadata\Factory\MetadataFactory;
use Oro\Bundle\EntityConfigBundle\Metadata\FieldMetadata;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderBag;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\EntityConfig\Mock\ConfigurationHandlerMock;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\Fixture\DemoEntity;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ConfigManagerTest extends TestCase
{
    private const ENTITY_CLASS = DemoEntity::class;

    private EventDispatcherInterface&MockObject $eventDispatcher;
    private MetadataFactory&MockObject $metadataFactory;
    private ConfigProvider&MockObject $configProvider;
    private ConfigModelManager&MockObject $modelManager;
    private ConfigCache&MockObject $configCache;
    private ConfigManager $configManager;
    private ConfigurationHandlerMock&MockObject $configurationHandler;

    #[\Override]
    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->configProvider->expects($this->any())
            ->method('getScope')
            ->willReturn('entity');

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->metadataFactory = $this->createMock(MetadataFactory::class);
        $this->modelManager = $this->createMock(ConfigModelManager::class);
        $this->configCache = $this->createMock(ConfigCache::class);
        $this->configurationHandler = $this->createMock(ConfigurationHandlerMock::class);
        $serviceProvider = new ServiceLocator([
            'annotation_metadata_factory' => function () {
                return $this->metadataFactory;
            },
            'configuration_handler' => function () {
                return $this->configurationHandler;
            },
            'event_dispatcher' => function () {
                return $this->eventDispatcher;
            },
            'audit_manager' => function () {
                return $this->createMock(AuditManager::class);
            },
            'config_model_manager' => function () {
                return $this->modelManager;
            }
        ]);
        $this->configManager = new ConfigManager(
            $this->configCache,
            $serviceProvider
        );
        $this->setProviderBag([$this->configProvider]);
    }

    public function testGetProviders(): void
    {
        $providers = $this->configManager->getProviders();
        $this->assertCount(1, $providers);
        $this->assertSame($this->configProvider, $providers['entity']);
    }

    public function testGetProvider(): void
    {
        $this->assertSame($this->configProvider, $this->configManager->getProvider('entity'));
    }

    public function testGetEntityMetadata(): void
    {
        $this->assertNull($this->configManager->getEntityMetadata('SomeUndefinedClass'));

        $metadata = $this->getEntityMetadata(self::ENTITY_CLASS);

        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $this->assertSame($metadata, $this->configManager->getEntityMetadata(self::ENTITY_CLASS));
    }

    public function testGetFieldMetadata(): void
    {
        $this->assertNull($this->configManager->getFieldMetadata('SomeUndefinedClass', 'entity'));

        $metadata = $this->getEntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = $this->getFieldMetadata(self::ENTITY_CLASS, 'id');
        $metadata->addFieldMetadata($idFieldMetadata);

        $this->metadataFactory->expects($this->exactly(2))
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $this->assertNull($this->configManager->getFieldMetadata(self::ENTITY_CLASS, 'undefinedField'));

        $this->assertSame(
            $metadata->fieldMetadata['id'],
            $this->configManager->getFieldMetadata(self::ENTITY_CLASS, 'id')
        );
    }

    /**
     * @dataProvider hasConfigProvider
     */
    public function testHasConfig(
        bool $expectedResult,
        bool $checkDatabaseResult,
        ?bool $cachedResult,
        ?ConfigModel $findModelResult,
        string $className,
        ?string $fieldName
    ): void {
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->willReturn($checkDatabaseResult);
        if ($checkDatabaseResult) {
            $this->configCache->expects($this->once())
                ->method('getConfigurable')
                ->with($className, $fieldName)
                ->willReturn($cachedResult);
            if (null === $cachedResult) {
                $this->configCache->expects($this->once())
                    ->method('saveConfigurable')
                    ->with($expectedResult, $className, $fieldName);
                if ($fieldName) {
                    $this->modelManager->expects($this->once())
                        ->method('findFieldModel')
                        ->with($className, $fieldName)
                        ->willReturn($findModelResult);
                } else {
                    $this->modelManager->expects($this->once())
                        ->method('findEntityModel')
                        ->with($className)
                        ->willReturn($findModelResult);
                }
            }
        }

        $result = $this->configManager->hasConfig($className, $fieldName);
        $this->assertEquals($expectedResult, $result);
    }

    public function hasConfigProvider(): array
    {
        return [
            'no database'          => [
                'expectedResult'      => false,
                'checkDatabaseResult' => false,
                'cachedResult'        => null,
                'findModelResult'     => null,
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => null
            ],
            'no database (field)'  => [
                'expectedResult'      => false,
                'checkDatabaseResult' => false,
                'cachedResult'        => null,
                'findModelResult'     => null,
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => 'id'
            ],
            'cached false'         => [
                'expectedResult'      => false,
                'checkDatabaseResult' => true,
                'cachedResult'        => false,
                'findModelResult'     => null,
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => null
            ],
            'cached false (field)' => [
                'expectedResult'      => false,
                'checkDatabaseResult' => true,
                'cachedResult'        => false,
                'findModelResult'     => null,
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => 'id'
            ],
            'cached true'          => [
                'expectedResult'      => true,
                'checkDatabaseResult' => true,
                'cachedResult'        => true,
                'findModelResult'     => null,
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => null
            ],
            'cached true (field)'  => [
                'expectedResult'      => true,
                'checkDatabaseResult' => true,
                'cachedResult'        => true,
                'findModelResult'     => null,
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => 'id'
            ],
            'no model'             => [
                'expectedResult'      => false,
                'checkDatabaseResult' => true,
                'cachedResult'        => null,
                'findModelResult'     => null,
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => null
            ],
            'no model (field)'     => [
                'expectedResult'      => false,
                'checkDatabaseResult' => true,
                'cachedResult'        => null,
                'findModelResult'     => null,
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => 'id'
            ],
            'has model'            => [
                'expectedResult'      => true,
                'checkDatabaseResult' => true,
                'cachedResult'        => null,
                'findModelResult'     => $this->createEntityConfigModel(self::ENTITY_CLASS),
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => null
            ],
            'has model (field)'    => [
                'expectedResult'      => true,
                'checkDatabaseResult' => true,
                'cachedResult'        => null,
                'findModelResult'     => $this->createFieldConfigModel(
                    $this->createEntityConfigModel(self::ENTITY_CLASS),
                    'id',
                    'int'
                ),
                'className'           => self::ENTITY_CLASS,
                'fieldName'           => 'id'
            ],
        ];
    }

    public function testGetConfigNoDatabase(): void
    {
        $this->expectException(LogicException::class);
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->willReturn(false);
        $this->configManager->getConfig($configId);
    }

    public function testGetConfigForNotConfigurable(): void
    {
        $this->expectException(RuntimeException::class);
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->willReturn(true);
        $this->configCache->expects($this->once())
            ->method('getConfigurable')
            ->with(self::ENTITY_CLASS)
            ->willReturn(false);
        $this->configManager->getConfig($configId);
    }

    public function testGetConfigForNewEntity(): void
    {
        $configId = new EntityConfigId('entity');

        $this->modelManager->expects($this->never())
            ->method('checkDatabase');

        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn(['translatable' => 'labelVal', 'other' => 'otherVal']);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->never())
            ->method('getTranslatableValues');
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);

        $config = $this->configManager->getConfig($configId);

        $expectedConfig = $this->getConfig(
            $configId,
            [
                'translatable' => 'labelVal',
                'other'        => 'otherVal'
            ]
        );

        $this->assertEquals($expectedConfig, $config);
        $this->configManager->calculateConfigChangeSet($config);
        $this->assertEquals(
            [
                'translatable' => [null, 'labelVal'],
                'other'        => [null, 'otherVal']
            ],
            $this->configManager->getConfigChangeSet($config)
        );
    }

    /**
     * @dataProvider getConfigCacheProvider
     */
    public function testGetConfigCache(ConfigIdInterface $configId, Config $cachedConfig): void
    {
        if ($configId instanceof FieldConfigId) {
            $this->configCache->expects($this->once())
                ->method('getFieldConfig')
                ->with($configId->getScope(), $configId->getClassName(), $configId->getFieldName())
                ->willReturn($cachedConfig);
        } else {
            $this->configCache->expects($this->once())
                ->method('getEntityConfig')
                ->with($configId->getScope(), $configId->getClassName())
                ->willReturn($cachedConfig);
        }

        $this->modelManager->expects($this->never())
            ->method('checkDatabase');
        $this->configCache->expects($this->never())
            ->method('getConfigurable');
        $this->modelManager->expects($this->never())
            ->method('getEntityModel');
        $this->modelManager->expects($this->never())
            ->method('getFieldModel');

        $result = $this->configManager->getConfig($configId);

        $this->assertSame($cachedConfig, $result);
        $this->configManager->calculateConfigChangeSet($result);
        $this->assertEquals(
            [],
            $this->configManager->getConfigChangeSet($result)
        );
    }

    public function getConfigCacheProvider(): array
    {
        return [
            'entity' => [
                new EntityConfigId('entity', self::ENTITY_CLASS),
                $this->getConfig(new EntityConfigId('entity', self::ENTITY_CLASS))
            ],
            'field'  => [
                new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int'),
                $this->getConfig(new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int'))
            ],
        ];
    }

    /**
     * @dataProvider getConfigNotCachedProvider
     */
    public function testGetConfigNotCached(
        ConfigIdInterface $configId,
        ConfigModel $getModelResult,
        Config $expectedConfig
    ): void {
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->willReturn(true);
        if ($configId instanceof FieldConfigId) {
            $this->configCache->expects($this->exactly(2))
                ->method('getConfigurable')
                ->willReturnMap([
                    [$configId->getClassName(), null, true],
                    [$configId->getClassName(), $configId->getFieldName(), true],
                ]);
            $this->configCache->expects($this->once())
                ->method('getFieldConfig')
                ->with($configId->getScope(), $configId->getClassName(), $configId->getFieldName())
                ->willReturn(null);
        } else {
            $this->configCache->expects($this->once())
                ->method('getConfigurable')
                ->with($configId->getClassName())
                ->willReturn(true);
            $this->configCache->expects($this->once())
                ->method('getEntityConfig')
                ->with($configId->getScope(), $configId->getClassName())
                ->willReturn(null);
        }
        $this->configCache->expects($this->once())
            ->method('saveConfig')
            ->with($expectedConfig);
        if ($configId instanceof FieldConfigId) {
            $this->modelManager->expects($this->never())
                ->method('getEntityModel');
            $this->modelManager->expects($this->once())
                ->method('getFieldModel')
                ->with($configId->getClassName(), $configId->getFieldName())
                ->willReturn($getModelResult);
        } else {
            $this->modelManager->expects($this->once())
                ->method('getEntityModel')
                ->with($configId->getClassName())
                ->willReturn($getModelResult);
            $this->modelManager->expects($this->never())
                ->method('getFieldModel');
        }

        $result = $this->configManager->getConfig($configId);

        $this->assertEquals($expectedConfig, $result);
        $this->configManager->calculateConfigChangeSet($result);
        $this->assertEquals(
            [],
            $this->configManager->getConfigChangeSet($result)
        );
    }

    public function getConfigNotCachedProvider(): array
    {
        return [
            'entity' => [
                new EntityConfigId('entity', self::ENTITY_CLASS),
                $this->createEntityConfigModel(self::ENTITY_CLASS),
                $this->getConfig(new EntityConfigId('entity', self::ENTITY_CLASS))
            ],
            'field'  => [
                new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int'),
                $this->createFieldConfigModel(
                    $this->createEntityConfigModel(self::ENTITY_CLASS),
                    'id',
                    'int'
                ),
                $this->getConfig(new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int'))
            ],
        ];
    }

    public function testEntityConfigChangeSet(): void
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $originalConfig = $this->getConfig(
            $configId,
            [
                'item1'  => true,
                'item11' => true,
                'item12' => true,
                'item2'  => 123,
                'item21' => 123,
                'item22' => 123,
                'item3'  => 'val2',
                'item4'  => 'val4',
                'item6'  => null,
                'item7'  => 'val7'
            ]
        );
        $this->configCache->expects($this->once())
            ->method('getEntityConfig')
            ->willReturn($originalConfig);
        $this->configManager->getConfig($configId);

        $changedConfigValues = [
            'item1' => true,
            'item11' => 1,
            'item12' => false,
            'item2' => 123,
            'item21' => '123',
            'item22' => 456,
            'item3' => 'val21',
            'item5' => 'val5',
            'item6' => 'val6',
            'item7' => null
        ];
        $changedConfig = $this->getConfig(
            $configId,
            $changedConfigValues
        );
        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn($changedConfigValues);
        $this->configManager->persist($changedConfig);

        $this->configManager->calculateConfigChangeSet($changedConfig);

        $expectedChangeSet = [
            'item12' => [true, false],
            'item22' => [123, 456],
            'item3'  => ['val2', 'val21'],
            'item5'  => [null, 'val5'],
            'item6'  => [null, 'val6'],
            'item7'  => ['val7', null]
        ];
        $this->assertEquals(
            $expectedChangeSet,
            $this->configManager->getConfigChangeSet($changedConfig)
        );
        $this->assertEquals(
            $expectedChangeSet,
            $this->configManager->getEntityConfigChangeSet(
                'entity',
                $configId->getClassName()
            )
        );
    }

    public function testFieldConfigChangeSet(): void
    {
        $configId = new FieldConfigId('entity', self::ENTITY_CLASS, 'testField');
        $originalConfig = $this->getConfig(
            $configId,
            [
                'item1'  => true,
                'item11' => true,
                'item12' => true,
                'item2'  => 123,
                'item21' => 123,
                'item22' => 123,
                'item3'  => 'val2',
                'item4'  => 'val4',
                'item6'  => null,
                'item7'  => 'val7'
            ]
        );
        $this->configCache->expects($this->once())
            ->method('getFieldConfig')
            ->willReturn($originalConfig);
        $this->configManager->getConfig($configId);

        $changedConfigValues = [
            'item1' => true,
            'item11' => 1,
            'item12' => false,
            'item2' => 123,
            'item21' => '123',
            'item22' => 456,
            'item3' => 'val21',
            'item5' => 'val5',
            'item6' => 'val6',
            'item7' => null
        ];
        $changedConfig = $this->getConfig(
            $configId,
            $changedConfigValues
        );
        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn($changedConfigValues);
        $this->configManager->persist($changedConfig);

        $this->configManager->calculateConfigChangeSet($changedConfig);

        $expectedChangeSet = [
            'item12' => [true, false],
            'item22' => [123, 456],
            'item3'  => ['val2', 'val21'],
            'item5'  => [null, 'val5'],
            'item6'  => [null, 'val6'],
            'item7'  => ['val7', null]
        ];
        $this->assertEquals(
            $expectedChangeSet,
            $this->configManager->getConfigChangeSet($changedConfig)
        );
        $this->assertEquals(
            $expectedChangeSet,
            $this->configManager->getFieldConfigChangeSet(
                'entity',
                $configId->getClassName(),
                $configId->getFieldName()
            )
        );
    }

    public function testGetIdsNoDatabase(): void
    {
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->willReturn(false);
        $result = $this->configManager->getIds('entity');
        $this->assertEquals([], $result);
    }

    /**
     * @dataProvider getIdsProvider
     */
    public function testGetIds(string $scope, ?string $className, bool $withHidden, array $expectedIds): void
    {
        $models = [
            $this->createEntityConfigModel('EntityClass1'),
            $this->createEntityConfigModel('EntityClass2'),
            $this->createEntityConfigModel('HiddenEntity', ConfigModel::MODE_HIDDEN),
        ];
        $entityModel = $this->createEntityConfigModel('EntityClass1');
        $fieldModels = [
            $this->createFieldConfigModel($entityModel, 'f1', 'int'),
            $this->createFieldConfigModel($entityModel, 'f2', 'int'),
            $this->createFieldConfigModel($entityModel, 'hiddenField', 'int', ConfigModel::MODE_HIDDEN),
        ];

        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->willReturn(true);
        $this->modelManager->expects($this->once())
            ->method('getModels')
            ->with($className)
            ->willReturn($className ? $fieldModels : $models);

        $result = $this->configManager->getIds($scope, $className, $withHidden);
        $this->assertEquals($expectedIds, array_values($result));
    }

    public function getIdsProvider(): array
    {
        return [
            [
                'entity',
                null,
                true,
                [
                    new EntityConfigId('entity', 'EntityClass1'),
                    new EntityConfigId('entity', 'EntityClass2'),
                    new EntityConfigId('entity', 'HiddenEntity'),
                ]
            ],
            [
                'entity',
                null,
                false,
                [
                    new EntityConfigId('entity', 'EntityClass1'),
                    new EntityConfigId('entity', 'EntityClass2'),
                ]
            ],
            [
                'entity',
                'EntityClass1',
                true,
                [
                    new FieldConfigId('entity', 'EntityClass1', 'f1', 'int'),
                    new FieldConfigId('entity', 'EntityClass1', 'f2', 'int'),
                    new FieldConfigId('entity', 'EntityClass1', 'hiddenField', 'int'),
                ]
            ],
            [
                'entity',
                'EntityClass1',
                false,
                [
                    new FieldConfigId('entity', 'EntityClass1', 'f1', 'int'),
                    new FieldConfigId('entity', 'EntityClass1', 'f2', 'int'),
                ]
            ],
        ];
    }

    /**
     * @dataProvider getConfigsProvider
     */
    public function testGetConfigs(string $scope, ?string $className, bool $withHidden, array $expectedConfigs): void
    {
        $models = [
            $this->createEntityConfigModel('EntityClass1'),
            $this->createEntityConfigModel('EntityClass2'),
            $this->createEntityConfigModel('HiddenEntity', ConfigModel::MODE_HIDDEN),
        ];
        $entityModel = $this->createEntityConfigModel('EntityClass1');
        $fieldModels = [
            $this->createFieldConfigModel($entityModel, 'f1', 'int'),
            $this->createFieldConfigModel($entityModel, 'f2', 'int'),
            $this->createFieldConfigModel($entityModel, 'hiddenField', 'int', ConfigModel::MODE_HIDDEN),
        ];

        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->willReturn(true);
        $this->configCache->expects($this->any())
            ->method('getConfigurable')
            ->willReturn(true);
        $this->modelManager->expects($this->once())
            ->method('getModels')
            ->with($className)
            ->willReturn($className ? $fieldModels : $models);
        if ($className) {
            $this->modelManager->expects($this->any())
                ->method('getFieldModel')
                ->willReturnMap([
                    [$className, 'f1', $fieldModels[0]],
                    [$className, 'f2', $fieldModels[1]],
                    [$className, 'hiddenField', $fieldModels[2]],
                ]);
        } else {
            $this->modelManager->expects($this->any())
                ->method('getEntityModel')
                ->willReturnMap([
                    ['EntityClass1', $models[0]],
                    ['EntityClass2', $models[1]],
                    ['HiddenEntity', $models[2]],
                ]);
        }

        $result = $this->configManager->getConfigs($scope, $className, $withHidden);
        $this->assertEquals($expectedConfigs, array_values($result));
    }

    public function getConfigsProvider(): array
    {
        return [
            [
                'entity',
                null,
                true,
                [
                    $this->getConfig(new EntityConfigId('entity', 'EntityClass1')),
                    $this->getConfig(new EntityConfigId('entity', 'EntityClass2')),
                    $this->getConfig(new EntityConfigId('entity', 'HiddenEntity')),
                ]
            ],
            [
                'entity',
                null,
                false,
                [
                    $this->getConfig(new EntityConfigId('entity', 'EntityClass1')),
                    $this->getConfig(new EntityConfigId('entity', 'EntityClass2')),
                ]
            ],
            [
                'entity',
                'EntityClass1',
                true,
                [
                    $this->getConfig(new FieldConfigId('entity', 'EntityClass1', 'f1', 'int')),
                    $this->getConfig(new FieldConfigId('entity', 'EntityClass1', 'f2', 'int')),
                    $this->getConfig(new FieldConfigId('entity', 'EntityClass1', 'hiddenField', 'int')),
                ]
            ],
            [
                'entity',
                'EntityClass1',
                false,
                [
                    $this->getConfig(new FieldConfigId('entity', 'EntityClass1', 'f1', 'int')),
                    $this->getConfig(new FieldConfigId('entity', 'EntityClass1', 'f2', 'int')),
                ]
            ],
        ];
    }

    public function testClearEntityCache(): void
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $this->configCache->expects($this->once())
            ->method('deleteEntityConfig')
            ->with($configId->getClassName());
        $this->configManager->clearCache($configId);
    }

    public function testClearFieldCache(): void
    {
        $configId = new FieldConfigId('entity', self::ENTITY_CLASS, 'field');
        $this->configCache->expects($this->once())
            ->method('deleteFieldConfig')
            ->with($configId->getClassName(), $configId->getFieldName());
        $this->configManager->clearCache($configId);
    }

    public function testClearCacheAll(): void
    {
        $this->configCache->expects($this->once())
            ->method('deleteAllConfigs');
        $this->configManager->clearCache();
    }

    public function testClearConfigurableCache(): void
    {
        $this->configCache->expects($this->once())
            ->method('deleteAllConfigurable');
        $this->modelManager->expects($this->once())
            ->method('clearCheckDatabase');
        $this->configManager->clearConfigurableCache();
    }

    public function testClearModelCache(): void
    {
        $this->modelManager->expects($this->once())
            ->method('clearCache');
        $this->configCache->expects($this->once())
            ->method('deleteAll')
            ->with(true);
        $this->configManager->clearModelCache();
    }

    public function testFlushAllCaches(): void
    {
        $this->configCache->expects($this->once())
            ->method('deleteAll');
        $this->configManager->flushAllCaches();
    }

    public function testHasConfigEntityModelWithNoModel(): void
    {
        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->willReturn(null);

        $result = $this->configManager->hasConfigEntityModel(self::ENTITY_CLASS);
        $this->assertFalse($result);
    }

    public function testHasConfigEntityModel(): void
    {
        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->willReturn($this->createEntityConfigModel(self::ENTITY_CLASS));

        $result = $this->configManager->hasConfigEntityModel(self::ENTITY_CLASS);
        $this->assertTrue($result);
    }

    public function testHasConfigFieldModelWithNoModel(): void
    {
        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->willReturn(null);

        $result = $this->configManager->hasConfigFieldModel(self::ENTITY_CLASS, 'id');
        $this->assertFalse($result);
    }

    public function testHasConfigFieldModel(): void
    {
        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->willReturn(
                $this->createFieldConfigModel(
                    $this->createEntityConfigModel(self::ENTITY_CLASS),
                    'id',
                    'int'
                )
            );

        $result = $this->configManager->hasConfigFieldModel(self::ENTITY_CLASS, 'id');
        $this->assertTrue($result);
    }

    public function testGetConfigEntityModel(): void
    {
        $model = $this->createEntityConfigModel(self::ENTITY_CLASS);

        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->willReturn($model);

        $result = $this->configManager->getConfigEntityModel(self::ENTITY_CLASS);
        $this->assertSame($model, $result);
    }

    public function testGetConfigFieldModel(): void
    {
        $model = $this->createFieldConfigModel(
            $this->createEntityConfigModel(self::ENTITY_CLASS),
            'id',
            'int'
        );

        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->willReturn($model);

        $result = $this->configManager->getConfigFieldModel(self::ENTITY_CLASS, 'id');
        $this->assertSame($model, $result);
    }

    /**
     * @dataProvider emptyNameProvider
     */
    public function testCreateConfigEntityModelForEmptyClassName(?string $className): void
    {
        $model = $this->createEntityConfigModel($className);

        $this->modelManager->expects($this->never())
            ->method('findEntityModel');
        $this->modelManager->expects($this->once())
            ->method('createEntityModel')
            ->with($className, ConfigModel::MODE_DEFAULT)
            ->willReturn($model);

        $result = $this->configManager->createConfigEntityModel($className);
        $this->assertSame($model, $result);
    }

    /**
     * @dataProvider emptyNameProvider
     */
    public function testCreateConfigEntityModelForEmptyClassNameAndMode(?string $className): void
    {
        $mode = ConfigModel::MODE_HIDDEN;
        $model = $this->createEntityConfigModel($className, $mode);

        $this->modelManager->expects($this->never())
            ->method('findEntityModel');
        $this->modelManager->expects($this->once())
            ->method('createEntityModel')
            ->with($className, $mode)
            ->willReturn($model);

        $result = $this->configManager->createConfigEntityModel($className, $mode);
        $this->assertSame($model, $result);
    }

    public function testCreateConfigEntityModelForExistingModel(): void
    {
        $model = $this->createEntityConfigModel(self::ENTITY_CLASS);

        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->willReturn($model);
        $this->modelManager->expects($this->never())
            ->method('createEntityModel');

        $result = $this->configManager->createConfigEntityModel(self::ENTITY_CLASS);
        $this->assertSame($model, $result);
    }

    /**
     * @dataProvider createConfigEntityModelProvider
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testCreateConfigEntityModel(
        ?string $mode,
        bool $hasMetadata,
        ?string $metadataMode,
        string $expectedMode,
        ?array $cachedEntities,
        ?array $expectedSavedCachedEntities
    ): void {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $model = $this->createEntityConfigModel(self::ENTITY_CLASS, $expectedMode);

        $metadata = null;
        if ($hasMetadata) {
            $metadata = $this->getEntityMetadata(
                self::ENTITY_CLASS,
                ['translatable' => 'labelVal', 'other' => 'otherVal']
            );
            if (null !== $metadataMode) {
                $metadata->mode = $metadataMode;
            }
        }

        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->willReturn(null);
        $this->modelManager->expects($this->once())
            ->method('createEntityModel')
            ->with(self::ENTITY_CLASS, $expectedMode)
            ->willReturn($model);
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $processedDefaultValues = $hasMetadata
            ? [
                'translatable' => 'labelVal',
                'other' => 'otherVal',
                'translatable10' => 'labelVal10',
                'other10' => 'otherVal10'
            ]
            : [
                'translatable10' => 'labelVal10',
                'other10' => 'otherVal10'
            ];
        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn($processedDefaultValues);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->willReturn(['translatable', 'translatable10', 'auto_generated']);
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                new EntityConfigEvent(self::ENTITY_CLASS, $this->configManager),
                Events::CREATE_ENTITY
            );

        $config = $this->getConfig(
            $configId,
            [
                'translatable'   => 'oro.entityconfig.tests.unit.fixture.demoentity.entity_translatable',
                'other10'        => 'otherVal10',
                'translatable10' => 'labelVal10',
                'auto_generated' => 'oro.entityconfig.tests.unit.fixture.demoentity.entity_auto_generated'
            ]
        );
        if ($metadata) {
            $config->set('other', 'otherVal');
            $config->set('translatable', 'labelVal');
        }

        $this->configCache->expects($this->once())
            ->method('saveConfig')
            ->with($config, true);

        $this->configCache->expects($this->once())
            ->method('saveConfigurable')
            ->with(true, self::ENTITY_CLASS, null, true);

        $this->configCache->expects($this->once())
            ->method('getEntities')
            ->with(true)
            ->willReturn($cachedEntities);
        if (null === $expectedSavedCachedEntities) {
            $this->configCache->expects($this->never())
                ->method('saveEntities');
        } else {
            $this->configCache->expects($this->once())
                ->method('saveEntities')
                ->with($expectedSavedCachedEntities, true);
        }

        $result = $this->configManager->createConfigEntityModel(self::ENTITY_CLASS, $mode);

        $this->assertEquals($model, $result);
        $this->assertEquals([$config], $this->configManager->getUpdateConfig());
    }

    public function createConfigEntityModelProvider(): array
    {
        return [
            [
                'mode'                        => null,
                'hasMetadata'                 => false,
                'metadataMode'                => null,
                'expectedMode'                => ConfigModel::MODE_DEFAULT,
                'cachedEntities'              => null,
                'expectedSavedCachedEntities' => null
            ],
            [
                'mode'                        => null,
                'hasMetadata'                 => true,
                'metadataMode'                => null,
                'expectedMode'                => ConfigModel::MODE_DEFAULT,
                'cachedEntities'              => null,
                'expectedSavedCachedEntities' => null
            ],
            [
                'mode'                        => null,
                'hasMetadata'                 => true,
                'metadataMode'                => ConfigModel::MODE_HIDDEN,
                'expectedMode'                => ConfigModel::MODE_HIDDEN,
                'cachedEntities'              => null,
                'expectedSavedCachedEntities' => null
            ],
            [
                'mode'                        => ConfigModel::MODE_HIDDEN,
                'hasMetadata'                 => false,
                'metadataMode'                => null,
                'expectedMode'                => ConfigModel::MODE_HIDDEN,
                'cachedEntities'              => null,
                'expectedSavedCachedEntities' => null
            ],
            [
                'mode'                        => ConfigModel::MODE_HIDDEN,
                'hasMetadata'                 => true,
                'metadataMode'                => null,
                'expectedMode'                => ConfigModel::MODE_HIDDEN,
                'cachedEntities'              => null,
                'expectedSavedCachedEntities' => null
            ],
            [
                'mode'                        => ConfigModel::MODE_DEFAULT,
                'hasMetadata'                 => true,
                'metadataMode'                => ConfigModel::MODE_HIDDEN,
                'expectedMode'                => ConfigModel::MODE_DEFAULT,
                'cachedEntities'              => null,
                'expectedSavedCachedEntities' => null
            ],
            [
                'mode'                        => null,
                'hasMetadata'                 => false,
                'metadataMode'                => null,
                'expectedMode'                => ConfigModel::MODE_DEFAULT,
                'cachedEntities'              => [],
                'expectedSavedCachedEntities' => [
                    self::ENTITY_CLASS => ['i' => null, 'h' => false]
                ]
            ],
            [
                'mode'                        => ConfigModel::MODE_HIDDEN,
                'hasMetadata'                 => false,
                'metadataMode'                => null,
                'expectedMode'                => ConfigModel::MODE_HIDDEN,
                'cachedEntities'              => [
                    'Test\AnotherEntity' => ['i' => 123, 'h' => false]
                ],
                'expectedSavedCachedEntities' => [
                    'Test\AnotherEntity' => ['i' => 123, 'h' => false],
                    self::ENTITY_CLASS   => ['i' => null, 'h' => true]
                ]
            ]
        ];
    }

    public function testCreateConfigFieldModelForExistingModel(): void
    {
        $model = $this->createFieldConfigModel(
            $this->createEntityConfigModel(self::ENTITY_CLASS),
            'id',
            'int'
        );

        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->willReturn($model);
        $this->modelManager->expects($this->never())
            ->method('createFieldModel');

        $result = $this->configManager->createConfigFieldModel(self::ENTITY_CLASS, 'id', 'int');
        $this->assertSame($model, $result);
    }

    /**
     * @dataProvider createConfigFieldModelProvider
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testCreateConfigFieldModel(
        ?string $mode,
        bool $hasMetadata,
        ?string $metadataMode,
        string $expectedMode,
        ?array $cachedFields,
        ?array $expectedSavedCachedFields
    ): void {
        $configId = new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int');
        $model = $this->createFieldConfigModel(
            $this->createEntityConfigModel(self::ENTITY_CLASS),
            'id',
            'int'
        );

        $metadata = null;
        if ($hasMetadata) {
            $metadata = $this->getEntityMetadata(self::ENTITY_CLASS);
            $idFieldMetadata = $this->getFieldMetadata(self::ENTITY_CLASS, 'id');
            $idFieldMetadata->defaultValues['entity'] = ['translatable' => 'labelVal', 'other' => 'otherVal'];
            $metadata->addFieldMetadata($idFieldMetadata);
            if (null !== $metadataMode) {
                $idFieldMetadata->mode = $metadataMode;
            }
        }

        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->willReturn(null);
        $this->modelManager->expects($this->once())
            ->method('createFieldModel')
            ->with(self::ENTITY_CLASS, 'id', 'int', $expectedMode)
            ->willReturn($model);
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $processedDefaultValues = $hasMetadata
            ? [
                'translatable' => 'labelVal',
                'other' => 'otherVal',
                'translatable10' => 'labelVal10',
                'other10' => 'otherVal10'
            ]
            : [
                'translatable10' => 'labelVal10',
                'other10' => 'otherVal10'
            ];
        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn($processedDefaultValues);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->willReturn(['translatable', 'translatable10', 'auto_generated']);
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                new FieldConfigEvent(self::ENTITY_CLASS, 'id', $this->configManager),
                Events::CREATE_FIELD
            );

        $config = $this->getConfig(
            $configId,
            [
                'translatable'   => 'oro.entityconfig.tests.unit.fixture.demoentity.id.translatable',
                'other10'        => 'otherVal10',
                'translatable10' => 'labelVal10',
                'auto_generated' => 'oro.entityconfig.tests.unit.fixture.demoentity.id.auto_generated'
            ]
        );
        if ($metadata) {
            $config->set('other', 'otherVal');
            $config->set('translatable', 'labelVal');
        }

        $this->configCache->expects($this->once())
            ->method('saveConfig')
            ->with($config, true);

        $this->configCache->expects($this->once())
            ->method('saveConfigurable')
            ->with(true, self::ENTITY_CLASS, 'id', true);

        $this->configCache->expects($this->once())
            ->method('getFields')
            ->with(self::ENTITY_CLASS, true)
            ->willReturn($cachedFields);
        if (null === $expectedSavedCachedFields) {
            $this->configCache->expects($this->never())
                ->method('saveFields');
        } else {
            $this->configCache->expects($this->once())
                ->method('saveFields')
                ->with(self::ENTITY_CLASS, $expectedSavedCachedFields, true);
        }

        $result = $this->configManager->createConfigFieldModel(self::ENTITY_CLASS, 'id', 'int', $mode);

        $this->assertEquals($model, $result);
        $this->assertEquals(
            [$config],
            $this->configManager->getUpdateConfig()
        );
    }

    public function createConfigFieldModelProvider(): array
    {
        return [
            [
                'mode'                      => null,
                'hasMetadata'               => false,
                'metadataMode'              => null,
                'expectedMode'              => ConfigModel::MODE_DEFAULT,
                'cachedFields'              => null,
                'expectedSavedCachedFields' => null
            ],
            [
                'mode'                      => null,
                'hasMetadata'               => true,
                'metadataMode'              => null,
                'expectedMode'              => ConfigModel::MODE_DEFAULT,
                'cachedFields'              => null,
                'expectedSavedCachedFields' => null
            ],
            [
                'mode'                      => null,
                'hasMetadata'               => true,
                'metadataMode'              => ConfigModel::MODE_HIDDEN,
                'expectedMode'              => ConfigModel::MODE_HIDDEN,
                'cachedFields'              => null,
                'expectedSavedCachedFields' => null
            ],
            [
                'mode'                      => ConfigModel::MODE_HIDDEN,
                'hasMetadata'               => false,
                'metadataMode'              => null,
                'expectedMode'              => ConfigModel::MODE_HIDDEN,
                'cachedFields'              => null,
                'expectedSavedCachedFields' => null
            ],
            [
                'mode'                      => ConfigModel::MODE_HIDDEN,
                'hasMetadata'               => true,
                'metadataMode'              => null,
                'expectedMode'              => ConfigModel::MODE_HIDDEN,
                'cachedFields'              => null,
                'expectedSavedCachedFields' => null
            ],
            [
                'mode'                      => ConfigModel::MODE_DEFAULT,
                'hasMetadata'               => true,
                'metadataMode'              => ConfigModel::MODE_HIDDEN,
                'expectedMode'              => ConfigModel::MODE_DEFAULT,
                'cachedFields'              => null,
                'expectedSavedCachedFields' => null
            ],
            [
                'mode'                      => null,
                'hasMetadata'               => false,
                'metadataMode'              => null,
                'expectedMode'              => ConfigModel::MODE_DEFAULT,
                'cachedFields'              => [],
                'expectedSavedCachedFields' => [
                    'id' => ['i' => null, 'h' => false, 't' => 'int']
                ]
            ],
            [
                'mode'                      => ConfigModel::MODE_HIDDEN,
                'hasMetadata'               => false,
                'metadataMode'              => null,
                'expectedMode'              => ConfigModel::MODE_HIDDEN,
                'cachedFields'              => [
                    'anotherField' => ['i' => 123, 'h' => false, 't' => 'string']
                ],
                'expectedSavedCachedFields' => [
                    'anotherField' => ['i' => 123, 'h' => false, 't' => 'string'],
                    'id'           => ['i' => null, 'h' => true, 't' => 'int']
                ]
            ]
        ];
    }

    public function testUpdateConfigEntityModelWithNoForce(): void
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $metadata = $this->getEntityMetadata(
            self::ENTITY_CLASS,
            [
                'translatable1' => 'labelVal1',
                'other1'        => 'otherVal1',
                'translatable2' => 'labelVal2',
                'other2'        => 'otherVal2',
            ]
        );

        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->willReturn($this->createEntityConfigModel(self::ENTITY_CLASS));
        $this->metadataFactory->expects($this->any())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn([
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'translatable2'  => 'labelVal2',
                'other2'         => 'otherVal2',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10'
            ]);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->willReturn(['translatable1', 'translatable2', 'translatable10', 'auto_generated']);
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);
        $config = $this->getConfig(
            $configId,
            [
                'translatable2' => 'labelVal2_old',
                'other2'        => 'otherVal2_old'
            ]
        );
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->willReturn($config);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::anything(), Events::UPDATE_ENTITY);

        $expectedConfig = $this->getConfig(
            $configId,
            [
                'translatable2'  => 'labelVal2_old',
                'other2'         => 'otherVal2_old',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10',
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'auto_generated' => 'oro.entityconfig.tests.unit.fixture.demoentity.entity_auto_generated'
            ]
        );

        $this->configManager->updateConfigEntityModel(self::ENTITY_CLASS);
        $this->assertEquals(
            $expectedConfig,
            $this->configManager->getUpdateConfig()[0]
        );
    }

    public function testUpdateConfigEntityModelWithForce(): void
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $metadata = $this->getEntityMetadata(
            self::ENTITY_CLASS,
            [
                'translatable1' => 'labelVal1',
                'other1'        => 'otherVal1',
                'translatable2' => 'labelVal2',
                'other2'        => 'otherVal2',
            ]
        );

        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->willReturn($this->createEntityConfigModel(self::ENTITY_CLASS));
        $this->metadataFactory->expects($this->any())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn([
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'translatable2'  => 'labelVal2',
                'other2'         => 'otherVal2',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10'
            ]);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->willReturn(['translatable1', 'translatable2', 'translatable10', 'auto_generated']);
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);
        $config = $this->getConfig(
            $configId,
            [
                'translatable2' => 'labelVal2_old',
                'other2'        => 'otherVal2_old'
            ]
        );
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->willReturn($config);

        $expectedConfig = $this->getConfig(
            $configId,
            [
                'translatable2'  => 'labelVal2',
                'other2'         => 'otherVal2',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10',
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'auto_generated' => 'oro.entityconfig.tests.unit.fixture.demoentity.entity_auto_generated'
            ]
        );

        $this->configManager->updateConfigEntityModel(self::ENTITY_CLASS, true);
        $this->assertEquals(
            $expectedConfig,
            $this->configManager->getUpdateConfig()[0]
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testUpdateConfigEntityModelWithForceForCustomEntity(): void
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $metadata = $this->getEntityMetadata(
            self::ENTITY_CLASS,
            [
                'translatable1' => 'labelVal1',
                'other1'        => 'otherVal1',
                'translatable2' => 'labelVal2',
                'other2'        => 'otherVal2',
            ]
        );

        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->willReturn($this->createEntityConfigModel(self::ENTITY_CLASS));
        $this->metadataFactory->expects($this->any())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $this->configurationHandler->expects($this->any())
            ->method('process')
            ->willReturn([
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'translatable2'  => 'labelVal2',
                'other2'         => 'otherVal2',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10'
            ]);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->willReturn(['translatable1', 'translatable2', 'translatable10', 'auto_generated']);
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);
        $config = $this->getConfig(
            $configId,
            [
                'translatable2' => 'labelVal2_old',
                'other2'        => 'otherVal2_old'
            ]
        );
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->willReturn($config);
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::anything(), Events::UPDATE_ENTITY);
        $extendConfig = $this->getConfig(new EntityConfigId('extend', self::ENTITY_CLASS));
        $extendConfig->set('owner', ExtendScope::OWNER_CUSTOM);
        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $extendConfigProvider->expects($this->any())
            ->method('getScope')
            ->willReturn('extend');
        $this->setProviderBag([$this->configProvider, $extendConfigProvider]);
        $extendConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY_CLASS)
            ->willReturn(true);
        $extendConfigProvider->expects($this->exactly(2))
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->willReturn($extendConfig);
        $extendPropertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $extendPropertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->willReturn([]);
        $extendConfigProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($extendPropertyConfigContainer);
        $expectedConfig = $this->getConfig(
            $configId,
            [
                'translatable2'  => 'labelVal2_old',
                'other2'         => 'otherVal2_old',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10',
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'auto_generated' => 'oro.entityconfig.tests.unit.fixture.demoentity.entity_auto_generated'
            ]
        );

        $this->configManager->updateConfigEntityModel(self::ENTITY_CLASS, true);
        $this->assertEquals(
            $expectedConfig,
            $this->configManager->getUpdateConfig()[0]
        );
    }

    public function testUpdateConfigFieldModelWithNoForce(): void
    {
        $configId = new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int');
        $metadata = $this->getEntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = $this->getFieldMetadata(
            self::ENTITY_CLASS,
            'id',
            [
                'translatable1' => 'labelVal1',
                'other1'        => 'otherVal1',
                'translatable2' => 'labelVal2',
                'other2'        => 'otherVal2',
            ]
        );
        $metadata->addFieldMetadata($idFieldMetadata);

        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn([
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'translatable2'  => 'labelVal2',
                'other2'         => 'otherVal2',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10'
            ]);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->willReturn(['translatable1', 'translatable2', 'translatable10', 'auto_generated']);
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);
        $config = $this->getConfig(
            $configId,
            [
                'translatable2' => 'labelVal2_old',
                'other2'        => 'otherVal2_old'
            ]
        );
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->willReturn($config);

        $expectedConfig = $this->getConfig(
            $configId,
            [
                'translatable2'  => 'labelVal2_old',
                'other2'         => 'otherVal2_old',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10',
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'auto_generated' => 'oro.entityconfig.tests.unit.fixture.demoentity.id.auto_generated'
            ]
        );

        $this->configManager->updateConfigFieldModel(self::ENTITY_CLASS, 'id');
        $this->assertEquals(
            $expectedConfig,
            $this->configManager->getUpdateConfig()[0]
        );
    }

    public function testUpdateConfigFieldModelWithForce(): void
    {
        $configId = new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int');
        $metadata = $this->getEntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = $this->getFieldMetadata(
            self::ENTITY_CLASS,
            'id',
            [
                'translatable1' => 'labelVal1',
                'other1'        => 'otherVal1',
                'translatable2' => 'labelVal2',
                'other2'        => 'otherVal2',
            ]
        );
        $metadata->addFieldMetadata($idFieldMetadata);

        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn([
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'translatable2'  => 'labelVal2',
                'other2'         => 'otherVal2',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10'
            ]);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->willReturn(['translatable1', 'translatable2', 'translatable10', 'auto_generated']);
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);
        $config = $this->getConfig(
            $configId,
            [
                'translatable2' => 'labelVal2_old',
                'other2'        => 'otherVal2_old'
            ]
        );
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->willReturn($config);

        $expectedConfig = $this->getConfig(
            $configId,
            [
                'translatable2'  => 'labelVal2',
                'other2'         => 'otherVal2',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10',
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'auto_generated' => 'oro.entityconfig.tests.unit.fixture.demoentity.id.auto_generated'
            ]
        );

        $this->configManager->updateConfigFieldModel(self::ENTITY_CLASS, 'id', true);
        $this->assertEquals(
            $expectedConfig,
            $this->configManager->getUpdateConfig()[0]
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testUpdateConfigFieldModelWithForceForCustomField(): void
    {
        $configId = new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int');
        $metadata = $this->getEntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = $this->getFieldMetadata(
            self::ENTITY_CLASS,
            'id',
            [
                'translatable1' => 'labelVal1',
                'other1'        => 'otherVal1',
                'translatable2' => 'labelVal2',
                'other2'        => 'otherVal2',
            ]
        );
        $metadata->addFieldMetadata($idFieldMetadata);

        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $this->configurationHandler->expects($this->exactly(2))
            ->method('process')
            ->willReturn([
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'translatable2'  => 'labelVal2',
                'other2'         => 'otherVal2',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10'
            ]);

        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->willReturn(['translatable1', 'translatable2', 'translatable10', 'auto_generated']);
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);
        $config = $this->getConfig(
            $configId,
            [
                'translatable2' => 'labelVal2_old',
                'other2'        => 'otherVal2_old'
            ]
        );
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->willReturn($config);

        $extendConfig = $this->getConfig(new FieldConfigId('extend', self::ENTITY_CLASS, 'id', 'int'));
        $extendConfig->set('owner', ExtendScope::OWNER_CUSTOM);
        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $extendConfigProvider->expects($this->any())
            ->method('getScope')
            ->willReturn('extend');
        $this->setProviderBag([$this->configProvider, $extendConfigProvider]);
        $extendConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY_CLASS, 'id')
            ->willReturn(true);
        $extendConfigProvider->expects($this->exactly(2))
            ->method('getConfig')
            ->with(self::ENTITY_CLASS, 'id')
            ->willReturn($extendConfig);
        $extendPropertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $extendPropertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->willReturn([]);
        $extendConfigProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->willReturn($extendPropertyConfigContainer);

        $expectedConfig = $this->getConfig(
            $configId,
            [
                'translatable2'  => 'labelVal2_old',
                'other2'         => 'otherVal2_old',
                'translatable10' => 'labelVal10',
                'other10'        => 'otherVal10',
                'translatable1'  => 'labelVal1',
                'other1'         => 'otherVal1',
                'auto_generated' => 'oro.entityconfig.tests.unit.fixture.demoentity.id.auto_generated'
            ]
        );

        $this->configManager->updateConfigFieldModel(self::ENTITY_CLASS, 'id', true);
        $this->assertEquals(
            $expectedConfig,
            $this->configManager->getUpdateConfig()[0]
        );
    }

    public function testPersistAndMerge(): void
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $values = ['val1' => '1', 'val2' => '1'];
        $config1 = $this->getConfig($configId, $values);
        $config2 = $this->getConfig($configId, ['val2' => '2_new', 'val3' => '3']);
        $expectedConfig = $this->getConfig($configId, ['val1' => '1', 'val2' => '2_new', 'val3' => '3']);
        $this->configurationHandler->expects($this->once())
            ->method('process')
            ->willReturn($values);
        $this->configManager->persist($config1);
        $this->configManager->merge($config2);
        $toBePersistedConfigs = $this->configManager->getUpdateConfig();

        $this->assertEquals([$expectedConfig], $toBePersistedConfigs);
    }

    public function testCreateFieldConfigByModel(): void
    {
        $scope = 'someScope';
        $entityClass = 'entityClass';
        $fieldName = 'someField';
        $fieldType = 'someType';
        $data = ['some' => 'data'];

        $entityConfigModel = new EntityConfigModel();
        $entityConfigModel->setClassName($entityClass);

        $fieldConfigModel = new FieldConfigModel($fieldName, $fieldType);
        $fieldConfigModel->setEntity($entityConfigModel);
        $fieldConfigModel->fromArray($scope, $data);

        $fieldConfig = $this->configManager->createFieldConfigByModel($fieldConfigModel, $scope);
        $expectedFieldConfig = new FieldConfigId($scope, $entityClass, $fieldName, $fieldType);

        $this->assertEquals($expectedFieldConfig, $fieldConfig->getId());
        $this->assertEquals($data, $fieldConfig->getValues());
    }

    /**
     * @param ConfigProvider[] $configProviders
     */
    private function setProviderBag(array $configProviders): void
    {
        $providers = [];
        foreach ($configProviders as $configProvider) {
            $providers[$configProvider->getScope()] = $configProvider;
        }

        $configProviderBag = $this->createMock(ConfigProviderBag::class);
        $configProviderBag->expects($this->any())
            ->method('getProvider')
            ->willReturnCallback(function ($scope) use ($providers) {
                return $providers[$scope] ?? null;
            });
        $configProviderBag->expects($this->any())
            ->method('getProviders')
            ->willReturn($providers);

        $this->configManager->setProviderBag($configProviderBag);
    }

    private function createEntityConfigModel(
        ?string $className,
        string $mode = ConfigModel::MODE_DEFAULT
    ): EntityConfigModel {
        $result = new EntityConfigModel($className);
        $result->setMode($mode);

        return $result;
    }

    private function createFieldConfigModel(
        EntityConfigModel $entityConfigModel,
        string $fieldName,
        string $fieldType,
        string $mode = ConfigModel::MODE_DEFAULT
    ): FieldConfigModel {
        $result = new FieldConfigModel($fieldName, $fieldType);
        $result->setEntity($entityConfigModel);
        $result->setMode($mode);

        return $result;
    }

    private function getEntityMetadata(string $className, ?array $defaultValues = null): EntityMetadata
    {
        $metadata = new EntityMetadata($className);
        $metadata->mode = ConfigModel::MODE_DEFAULT;
        if (null !== $defaultValues) {
            $metadata->defaultValues['entity'] = $defaultValues;
        }

        return $metadata;
    }

    private function getFieldMetadata(string $className, string $fieldName, ?array $defaultValues = null): FieldMetadata
    {
        $metadata = new FieldMetadata($className, $fieldName);
        if (null !== $defaultValues) {
            $metadata->defaultValues['entity'] = $defaultValues;
        }

        return $metadata;
    }

    private function getConfig(ConfigIdInterface $configId, ?array $values = null): Config
    {
        $config = new Config($configId);
        if (null !== $values) {
            $config->setValues($values);
        }

        return $config;
    }

    public function emptyNameProvider(): array
    {
        return [
            [null],
            [''],
        ];
    }
}
