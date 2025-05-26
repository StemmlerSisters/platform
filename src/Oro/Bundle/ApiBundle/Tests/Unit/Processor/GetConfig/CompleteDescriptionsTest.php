<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetConfig;

use Doctrine\Inflector\Rules\English\InflectorFactory;
use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\ApiBundle\ApiDoc\EntityDescriptionProvider;
use Oro\Bundle\ApiBundle\ApiDoc\EntityNameProvider;
use Oro\Bundle\ApiBundle\ApiDoc\ResourceDocParserInterface;
use Oro\Bundle\ApiBundle\ApiDoc\ResourceDocParserRegistry;
use Oro\Bundle\ApiBundle\ApiDoc\ResourceDocProvider;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\Extra\DescriptionsConfigExtra;
use Oro\Bundle\ApiBundle\Config\FiltersConfig;
use Oro\Bundle\ApiBundle\Model\Label;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\DescriptionProcessor;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\EntityDescriptionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\FieldsDescriptionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\FiltersDescriptionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\IdentifierDescriptionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\RequestDependedTextProcessor;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\ResourceDocParserProvider;
use Oro\Bundle\ApiBundle\Provider\ResourcesProvider;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\ProductPrice as TestEntity;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\UserProfile as TestEntityWithInherit;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\ConfigProviderMock;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class CompleteDescriptionsTest extends ConfigProcessorTestCase
{
    private const string ID_DESCRIPTION = 'The unique identifier of a resource.';
    private const string REQUIRED_ID_DESCRIPTION = '<p>The unique identifier of a resource.</p>'
        . '<p><strong>The required field.</strong></p>';
    private const string CREATED_AT_DESCRIPTION = 'The date and time of resource record creation.';
    private const string UPDATED_AT_DESCRIPTION = 'The date and time of the last update of the resource record.';
    private const string OWNER_DESCRIPTION = 'An owner record represents'
        . ' the ownership capabilities of the record.';
    private const string ORGANIZATION_DESCRIPTION = 'An organization record represents'
        . ' a real enterprise, business, firm, company or another organization to which the users belong.';
    private const string FIELD_FILTER_DESCRIPTION = 'Filter records by \'%s\' field.';
    private const string ASSOCIATION_FILTER_DESCRIPTION = 'Filter records by \'%s\' relationship.';

    private ResourcesProvider&MockObject $resourcesProvider;
    private EntityDescriptionProvider&MockObject $entityDescriptionProvider;
    private ResourceDocProvider&MockObject $resourceDocProvider;
    private ResourceDocParserInterface&MockObject $resourceDocParser;
    private TranslatorInterface&MockObject $translator;
    private DoctrineHelper&MockObject $doctrineHelper;
    private ConfigProviderMock $ownershipConfigProvider;
    private CompleteDescriptions $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->resourcesProvider = $this->createMock(ResourcesProvider::class);
        $this->entityDescriptionProvider = $this->createMock(EntityDescriptionProvider::class);
        $this->resourceDocProvider = $this->createMock(ResourceDocProvider::class);
        $this->resourceDocParser = $this->createMock(ResourceDocParserInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->ownershipConfigProvider = new ConfigProviderMock($this->createMock(ConfigManager::class), 'ownership');

        $resourceDocParserRegistry = $this->createMock(ResourceDocParserRegistry::class);
        $resourceDocParserRegistry->expects(self::any())
            ->method('getParser')
            ->willReturn($this->resourceDocParser);

        $resourceDocParserProvider = new ResourceDocParserProvider($resourceDocParserRegistry);
        $descriptionProcessor = new DescriptionProcessor(
            new RequestDependedTextProcessor()
        );
        $identifierDescriptionHelper = new IdentifierDescriptionHelper($this->doctrineHelper);

        $this->processor = new CompleteDescriptions(
            $this->resourcesProvider,
            new EntityDescriptionHelper(
                $this->entityDescriptionProvider,
                new EntityNameProvider($this->entityDescriptionProvider, (new InflectorFactory())->build()),
                $this->translator,
                $this->resourceDocProvider,
                $resourceDocParserProvider,
                $descriptionProcessor,
                $identifierDescriptionHelper,
                -1,
                100
            ),
            new FieldsDescriptionHelper(
                $this->entityDescriptionProvider,
                $this->translator,
                $resourceDocParserProvider,
                $descriptionProcessor,
                $identifierDescriptionHelper,
                $this->ownershipConfigProvider
            ),
            new FiltersDescriptionHelper(
                $this->translator,
                $resourceDocParserProvider,
                $descriptionProcessor
            )
        );

        $this->context->setClassName(TestEntity::class);
    }

    public function testWithoutTargetAction(): void
    {
        $config = [
            'exclusion_policy'       => 'all',
            'identifier_field_names' => ['id'],
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_field_names' => ['id'],
                'fields'                 => [
                    'id'     => null,
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierDescriptionWhenItDoesNotExist(): void
    {
        $config = [];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierDescriptionWhenItAlreadyExists(): void
    {
        $config = [
            'identifier_description' => 'identifier description'
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => 'identifier description'
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierField(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForUpdateAction(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('update');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::REQUIRED_ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForCreateActionAndNotManageableEntity(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with(TestEntity::class)
            ->willReturn(false);

        $this->context->setTargetAction('create');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::REQUIRED_ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForCreateActionAndManageableEntityWithoutIdGenerator(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with(TestEntity::class)
            ->willReturn(true);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityMetadataForClass')
            ->with(TestEntity::class)
            ->willReturn($classMetadata);
        $classMetadata->expects(self::once())
            ->method('usesIdGenerator')
            ->willReturn(false);

        $this->context->setTargetAction('create');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::REQUIRED_ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForCreateActionAndManageableEntityWithIdGenerator(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with(TestEntity::class)
            ->willReturn(true);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityMetadataForClass')
            ->with(TestEntity::class)
            ->willReturn($classMetadata);
        $classMetadata->expects(self::once())
            ->method('usesIdGenerator')
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getIdentifierFieldNames')
            ->willReturn(['id']);

        $this->context->setTargetAction('create');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForCreateActionAndManageableEntityWithIdGeneratorButApiIdNotEqualEntityId(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with(TestEntity::class)
            ->willReturn(true);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityMetadataForClass')
            ->with(TestEntity::class)
            ->willReturn($classMetadata);
        $classMetadata->expects(self::once())
            ->method('usesIdGenerator')
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getIdentifierFieldNames')
            ->willReturn(['entityId']);

        $this->context->setTargetAction('create');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::REQUIRED_ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldWhenIdentifierDescriptionIsSet(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'identifier_description' => 'identifier field description',
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => 'identifier field description',
                'fields'                 => [
                    'id'     => [
                        'description' => 'identifier field description'
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForUpdateActionWhenIdentifierDescriptionIsSet(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'identifier_description' => 'identifier field description',
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('update');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => 'identifier field description',
                'fields'                 => [
                    'id'     => [
                        'description' => '<p>identifier field description</p>'
                            . '<p><strong>The required field.</strong></p>'
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldWhenItAlreadyHasDescription(): void
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => [
                    'description' => 'existing description'
                ],
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => 'existing description'
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testRenamedIdentifierField(): void
    {
        $config = [
            'identifier_field_names' => ['id1'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'  => null,
                'id1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id1'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'  => null,
                    'id1' => [
                        'description' => self::ID_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testCompositeIdentifierField(): void
    {
        $config = [
            'identifier_field_names' => ['id1', 'id2'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'  => null,
                'id1' => null,
                'id2' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id1', 'id2'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'  => null,
                    'id1' => null,
                    'id2' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldDoesNotExist(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => null,
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testCreatedAtField(): void
    {
        $config = [
            'fields' => [
                'id'        => null,
                'createdAt' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'        => null,
                    'createdAt' => [
                        'description' => self::CREATED_AT_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testCreatedAtFieldWhenItAlreadyHasDescription(): void
    {
        $config = [
            'fields' => [
                'id'        => null,
                'createdAt' => [
                    'description' => 'existing description'
                ]
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'        => null,
                    'createdAt' => [
                        'description' => 'existing description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testUpdatedAtField(): void
    {
        $config = [
            'fields' => [
                'id'        => null,
                'created'   => null,
                'updatedAt' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'        => null,
                    'created'   => null,
                    'updatedAt' => [
                        'description' => self::UPDATED_AT_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testUpdatedAtFieldWhenItAlreadyHasDescription(): void
    {
        $config = [
            'fields' => [
                'id'        => null,
                'updatedAt' => [
                    'description' => 'existing description'
                ]
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'        => null,
                    'updatedAt' => [
                        'description' => 'existing description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOwnershipFieldsForNonConfigurableEntity(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'        => null,
                'organization' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'        => null,
                    'organization' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOwnershipFieldsWithoutConfiguration(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'        => null,
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            []
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'        => null,
                    'organization' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOwnerField(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'        => null,
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            ['owner_field_name' => 'owner']
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'        => [
                        'description' => self::OWNER_DESCRIPTION
                    ],
                    'organization' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOrganizationField(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'        => null,
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            ['organization_field_name' => 'organization']
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'        => null,
                    'organization' => [
                        'description' => self::ORGANIZATION_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testRenamedOwnerField(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner2'       => ['property_path' => 'owner1'],
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            ['owner_field_name' => 'owner1']
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner2'       => [
                        'property_path' => 'owner1',
                        'description'   => self::OWNER_DESCRIPTION
                    ],
                    'organization' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testRenamedOrganizationField(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'         => null,
                'organization2' => ['property_path' => 'organization1']
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            ['organization_field_name' => 'organization1']
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'         => null,
                    'organization2' => [
                        'property_path' => 'organization1',
                        'description'   => self::ORGANIZATION_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOwnerFieldWithAdditionalDescription(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            $entityClass,
            ['owner_field_name' => 'owner']
        );

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'owner', $targetAction, 'action field description. {@inheritdoc}'],
                [$entityClass, 'owner', null, null]
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner' => [
                        'description' => 'action field description. ' . self::OWNER_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOrganizationFieldWithAdditionalDescription(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            $entityClass,
            ['organization_field_name' => 'organization']
        );

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'organization', $targetAction, 'action field description. {@inheritdoc}'],
                [$entityClass, 'organization', null, null]
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'organization' => [
                        'description' => 'action field description. ' . self::ORGANIZATION_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInConfig(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'field description'
                ]
            ]
        ];

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItIsLabelObject(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => new Label('field description label')
                ]
            ]
        ];

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('field description label')
            ->willReturn('translated field description');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'translated field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInConfigAndContainsInheritDocPlaceholder(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'field description, {@inheritdoc}'
                ]
            ]
        ];

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description, field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForRenamedFieldWhenItExistsInConfigAndContainsInheritDocPlaceholder(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'renamedField' => [
                    'property_path' => 'testField',
                    'description' => 'field description, {@inheritdoc}'
                ]
            ]
        ];

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'renamedField' => [
                        'property_path' => 'testField',
                        'description'   => 'field description, field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInConfigAndContainsDescriptionInheritDocPlaceholder(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'field description, {@inheritdoc:description}'
                ]
            ]
        ];

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description, field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInDocFile(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField', $targetAction)
            ->willReturn('field description');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInDocFileAndContainsInheritDocPlaceholder(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, 'common field description'],
                [$entityClass, 'testField', $targetAction, 'action field description. {@inheritdoc}']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'action field description. common field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescrWhenItExistsInDocFileAndContainsInheritDocPlaceholderAndWhenItExistsInConfig(): void
    {
        $entityClass = TestEntityWithInherit::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'field description from config'
                ]
            ]
        ];

        $this->resourcesProvider->expects(self::once())
            ->method('isResourceKnown')
            ->with($entityClass, $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn(true);

        $this->resourceDocParser->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField', $targetAction)
            ->willReturn('action field description. {@inheritdoc}');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'action field description. field description from config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFilterDescriptionWhenItExistsInConfigForEntityWithInherit(): void
    {
        $entityClass = TestEntityWithInherit::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'filter description'
                ]
            ]
        ];

        $this->resourcesProvider->expects(self::once())
            ->method('isResourceKnown')
            ->with($entityClass, $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn(true);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => 'filter description'
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFieldDescrWhenItExistsInDocFileAndContainsInheritDocPlaceholderButNoAndCommonDescription(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, null],
                [$entityClass, 'testField', $targetAction, 'action field description. {@inheritdoc}']
            ]);
        $this->entityDescriptionProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'action field description. field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItDoesNotExistInDocFileButExistCommonDescription(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, 'common field description'],
                [$entityClass, 'testField', $targetAction, null]
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'common field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescrWhenItDoesNotExistInDocFileButExistCommonDescriptionWithInheritDocPlaceholder(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, 'common field description. {@inheritdoc}'],
                [$entityClass, 'testField', $targetAction, null]
            ]);
        $this->entityDescriptionProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'common field description. field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItAndCommonDescriptionDoNotExistInDocFile(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, null],
                [$entityClass, 'testField', $targetAction, null]
            ]);
        $this->entityDescriptionProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForNestedField(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'fields' => [
                        'nestedField' => null
                    ]
                ]
            ]
        ];

        $this->entityDescriptionProvider->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null],
                [$entityClass, 'testField.nestedField', 'nested field description']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'fields' => [
                            'nestedField' => [
                                'description' => 'nested field description'
                            ]
                        ]
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForNestedFieldWhenFieldIsRenamed(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'renamedField' => [
                    'property_path' => 'testField',
                    'fields'        => [
                        'nestedField' => null
                    ]
                ]
            ]
        ];

        $this->entityDescriptionProvider->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null],
                [$entityClass, 'testField.nestedField', 'nested field description']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'renamedField' => [
                        'property_path' => 'testField',
                        'fields'        => [
                            'nestedField' => [
                                'description' => 'nested field description'
                            ]
                        ]
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForRenamedNestedField(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'fields' => [
                        'renamedNestedField' => [
                            'property_path' => 'nestedField'
                        ]
                    ]
                ]
            ]
        ];

        $this->entityDescriptionProvider->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null],
                [$entityClass, 'testField.nestedField', 'nested field description']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'fields' => [
                            'renamedNestedField' => [
                                'property_path' => 'nestedField',
                                'description'   => 'nested field description'
                            ]
                        ]
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForAssociationField(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'target_class' => 'Test\AssociationEntity',
                    'fields'       => [
                        'associationField' => null
                    ]
                ]
            ]
        ];

        $this->entityDescriptionProvider->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null],
                [$entityClass, 'associationField', 'association field description']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'target_class' => 'Test\AssociationEntity',
                        'fields'       => [
                            'associationField' => [
                                'description' => 'association field description'
                            ]
                        ]
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFilterDescriptionWhenItExistsInConfig(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'filter description'
                ]
            ]
        ];

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => 'filter description'
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFilterDescriptionWhenItIsLabelObject(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => new Label('filter description label')
                ]
            ]
        ];

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('filter description label')
            ->willReturn('translated filter description');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => 'translated filter description'
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFilterDescriptionWhenItExistsInDocFile(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::once())
            ->method('getFilterDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('filter description');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => 'filter description'
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFilterDescriptionForRegularField(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::once())
            ->method('getFilterDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn(null);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => sprintf(self::FIELD_FILTER_DESCRIPTION, 'testField')
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFilterDescriptionForAssociation(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'fields' => [
                        'id' => null
                    ]
                ]
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::once())
            ->method('getFilterDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn(null);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => sprintf(self::ASSOCIATION_FILTER_DESCRIPTION, 'testField')
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testEntityDocumentationForGetListActionWhenThereIsMaxResultsLimit(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'Test documentation',
            'max_results'      => 1000
        ];

        $this->context->getRequestType()->set(new RequestType([RequestType::JSON_API]));
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'max_results'            => 1000,
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'Test documentation'
                    . '<p><strong>Note:</strong>'
                    . ' The maximum number of records this endpoint can return is 1000.</p>'
            ],
            $this->context->getResult()
        );
    }

    public function testEntityDocumentationForDeleteListActionWhenThereIsMaxResultsLimit(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'Test documentation',
            'max_results'      => 1000
        ];

        $this->context->getRequestType()->set(new RequestType([RequestType::JSON_API]));
        $this->context->setTargetAction('delete_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'max_results'            => 1000,
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'Test documentation'
                    . '<p><strong>Note:</strong>'
                    . ' The maximum number of records this endpoint can delete at a time is 1000.</p>'
            ],
            $this->context->getResult()
        );
    }

    public function testRequestDependedContentForEntityDocumentation(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => '{@request:json_api}JSON API{@/request}{@request:rest}REST{@/request}'
        ];

        $this->context->getRequestType()->set(new RequestType([RequestType::JSON_API]));
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'JSON API'
            ],
            $this->context->getResult()
        );
    }

    public function testRequestDependedContentForFieldDescription(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'field1' => [
                    'description' => '{@request:json_api}JSON API{@/request}{@request:rest}REST{@/request}'
                ]
            ]
        ];

        $this->context->getRequestType()->set(new RequestType([RequestType::JSON_API]));
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'field1' => [
                        'description' => 'JSON API'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testRequestDependedContentForFilterDescription(): void
    {
        $filters = new FiltersConfig();
        $filter1 = $filters->addField('field1');
        $filter1->setDescription('{@request:json_api}JSON API{@/request}{@request:rest}REST{@/request}');

        $this->context->getRequestType()->set(new RequestType([RequestType::JSON_API]));
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject([]));
        $this->context->setFilters($filters);
        $this->processor->process($this->context);

        self::assertEquals('JSON API', $filter1->getDescription());
    }

    public function testPrimaryResourceDescriptionWhenItExistsInConfig(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'description'      => 'test description'
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => 'test description'
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDescriptionWhenItExistsInConfig(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'description'      => 'test description'
        ];

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setAssociationName('testAssociation');
        $this->context->setTargetAction('get_subresource');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => 'test description'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionWhenItIsLabelObject(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'description'      => new Label('description_label')
        ];

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('description_label')
            ->willReturn('translated description');

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => 'translated description'
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDescriptionWhenItIsLabelObject(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'description'      => new Label('description_label')
        ];

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('description_label')
            ->willReturn('translated description');

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setAssociationName('testAssociation');
        $this->context->setTargetAction('get_subresource');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => 'translated description'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionWhenEntityDescriptionProviderReturnsNull(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $entityDescription = 'Product Price';
        $actionDescription = 'Get Product Price';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityDescription')
            ->with($entityClass)
            ->willReturn(null);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDescription')
            ->with($targetAction, $entityDescription)
            ->willReturn($actionDescription);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $actionDescription
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionWhenEntityDescriptionProviderReturnsNullForCollectionResource(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $entityDescription = 'Product Prices';
        $actionDescription = 'Get list of Product Prices';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityPluralDescription')
            ->with($entityClass)
            ->willReturn(null);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDescription')
            ->with($targetAction, $entityDescription)
            ->willReturn($actionDescription);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setIsCollection(true);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $actionDescription
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionLoadedByEntityDescriptionProvider(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $entityDescription = 'some entity';
        $actionDescription = 'Get some entity';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityDescription')
            ->with($entityClass)
            ->willReturn($entityDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDescription')
            ->with($targetAction, $entityDescription)
            ->willReturn($actionDescription);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $actionDescription
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDescriptionLoadedByEntityDescriptionProvider(): void
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_subresource';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $associationDescription = 'test association';
        $subresourceDescription = 'Get test association';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDescription')
            ->with($targetAction, $associationDescription, false)
            ->willReturn($subresourceDescription);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $subresourceDescription
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionLoadedByEntityDescriptionProviderForCollectionResource(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $entityDescription = 'some entities';
        $actionDescription = 'Get list of some entities';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityPluralDescription')
            ->with($entityClass)
            ->willReturn($entityDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDescription')
            ->with($targetAction, $entityDescription)
            ->willReturn($actionDescription);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setIsCollection(true);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $actionDescription
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDescriptionLoadedByEntityDescriptionProviderForCollectionResource(): void
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_subresource';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $associationDescription = 'test association';
        $subresourceDescription = 'Get test association';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDescription')
            ->with($targetAction, $associationDescription, true)
            ->willReturn($subresourceDescription);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setIsCollection(true);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $subresourceDescription
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceRegisterDocumentationResources(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy'       => 'all',
            'documentation_resource' => ['foo_file.md', 'bar_file.md']
        ];
        $actionDocumentation = 'action description';

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('registerDocumentationResource')
            ->withConsecutive(['foo_file.md'], ['bar_file.md']);
        $this->resourceDocParser->expects(self::once())
            ->method('getActionDocumentation')
            ->with($entityClass, $targetAction)
            ->willReturn($actionDocumentation);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'documentation_resource' => ['foo_file.md', 'bar_file.md'],
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $actionDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceRegisterDocumentationResources(): void
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy'       => 'all',
            'documentation_resource' => ['documentation.md']
        ];
        $subresourceDocumentation = 'subresource description';

        $this->resourceDocParser->expects(self::once())
            ->method('registerDocumentationResource')
            ->with('documentation.md');
        $this->resourceDocParser->expects(self::once())
            ->method('getSubresourceDocumentation')
            ->with($parentEntityClass, $associationName, $targetAction)
            ->willReturn($subresourceDocumentation);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'documentation_resource' => ['documentation.md'],
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $subresourceDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenItExistsInConfig(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDocumentationWhenItExistsInConfig(): void
    {
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ];

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setAssociationName('testAssociation');
        $this->context->setTargetAction('get_subresource');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWithInheritDocPlaceholder(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'documentation' => 'action documentation. {@inheritdoc}'
        ];

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityDocumentation')
            ->with($entityClass)
            ->willReturn('entity documentation');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'action documentation. entity documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWithDescriptionInheritDocPlaceholder(): void
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'action documentation. {@inheritdoc:description}'
        ];

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityDocumentation')
            ->with($entityClass)
            ->willReturn('entity documentation');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'action documentation. entity documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationLoadedByResourceDocProvider(): void
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $singularEntityDescription = 'some entity';
        $pluralEntityDescription = 'some entities';
        $resourceDocumentation = 'Get some entity';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityDescription')
            ->with($entityClass)
            ->willReturn($singularEntityDescription);
        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityPluralDescription')
            ->with($entityClass)
            ->willReturn($pluralEntityDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDocumentation')
            ->with($targetAction, $singularEntityDescription, $pluralEntityDescription)
            ->willReturn($resourceDocumentation);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $resourceDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDocumentationLoadedByResourceDocProvider(): void
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_subresource';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $associationDescription = 'test association';
        $subresourceDocumentation = 'Get test association';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDocumentation')
            ->with($targetAction, $associationDescription, false)
            ->willReturn($subresourceDocumentation);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $subresourceDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDocumentationLoadedByResourceDocProviderForCollectionResource(): void
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_subresource';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $associationDescription = 'test association';
        $subresourceDocumentation = 'Get test association';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDocumentation')
            ->with($targetAction, $associationDescription, true)
            ->willReturn($subresourceDocumentation);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setIsCollection(true);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $subresourceDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testChangeSubresourceDocumentationWithoutCustomRequestDocumentationAction(): void
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'add_subresource';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $associationDescription = 'test association';
        $subresourceDocumentation = 'Change test association';
        $fieldDocumentation = 'field documentation';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDocumentation')
            ->with($targetAction, $associationDescription, false)
            ->willReturn($subresourceDocumentation);
        $this->resourceDocParser->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($parentEntityClass, 'testField', $targetAction)
            ->willReturn($fieldDocumentation);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setExtra(new DescriptionsConfigExtra());
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $subresourceDocumentation,
                'fields'                 => [
                    'testField' => [
                        'description' => $fieldDocumentation
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testChangeSubresourceDocumentationWithCustomRequestDocumentationAction(): void
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'add_subresource';
        $requestDocumentationAction = 'some_action';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $associationDescription = 'test association';
        $subresourceDocumentation = 'Change test association';
        $fieldDocumentation = 'field documentation';

        $this->entityDescriptionProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDocumentation')
            ->with($targetAction, $associationDescription, false)
            ->willReturn($subresourceDocumentation);
        $this->resourceDocParser->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($parentEntityClass, 'testField', $requestDocumentationAction)
            ->willReturn($fieldDocumentation);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setExtra(new DescriptionsConfigExtra($requestDocumentationAction));
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $subresourceDocumentation,
                'fields'                 => [
                    'testField' => [
                        'description' => $fieldDocumentation
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    /**
     * @dataProvider preventingDoubleParagraphTagWhenInheritDocPlaceholderIsReplacedWithInheritedTextProvider
     */
    public function testPreventingDoubleParagraphTagWhenInheritDocPlaceholderIsReplacedWithInheritedText(
        string $mainText,
        ?string $inheritDocText,
        string $expectedText
    ): void {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => $mainText
        ];

        $this->entityDescriptionProvider->expects(self::once())
            ->method('getEntityDocumentation')
            ->with($entityClass)
            ->willReturn($inheritDocText);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $expectedText
            ],
            $this->context->getResult()
        );
    }

    public function preventingDoubleParagraphTagWhenInheritDocPlaceholderIsReplacedWithInheritedTextProvider(): array
    {
        return [
            'no paragraph tag'                                   => [
                'pre {@inheritdoc} post',
                'injection',
                'pre injection post'
            ],
            'null in inheritdoc text'                            => [
                'pre {@inheritdoc} post',
                null,
                'pre  post'
            ],
            'paragraph tag in main text'                         => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                'injection',
                '<p>pre</p><p>injection</p><p>post</p>'
            ],
            'paragraph tag in inheritdoc text'                   => [
                'pre {@inheritdoc} post',
                '<p>injection</p>',
                'pre injection post'
            ],
            'paragraph tag in both main and inheritdoc texts'    => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<p>injection</p>',
                '<p>pre</p><p>injection</p><p>post</p>'
            ],
            'several paragraph tags in inheritdoc text'          => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<p>injection</p><p>text</p>',
                '<p>pre</p><p>injection</p><p>text</p><p>post</p>'
            ],
            'paragraph tag in begin of inheritdoc text'          => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<p>injection</p><b>text</b>',
                '<p>pre</p><p>injection</p><b>text</b><p>post</p>'
            ],
            'paragraph tag in end of inheritdoc text'            => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<b>injection</b><p>text</p>',
                '<p>pre</p><b>injection</b><p>text</p><p>post</p>'
            ],
            'paragraph tag in middle of inheritdoc text'         => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<b>some</b><p>injection</p><b>text</b>',
                '<p>pre</p><b>some</b><p>injection</p><b>text</b><p>post</p>'
            ],
            'paragraph tags in begin and end of inheritdoc text' => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<p>some</p><b>injection</b><p>text</p>',
                '<p>pre</p><p>some</p><b>injection</b><p>text</p><p>post</p>'
            ]
        ];
    }

    /**
     * @dataProvider upsertTargetActionDataProvider
     */
    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedById(string $targetAction): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->setAllowedById(true);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['id']],
                'documentation'          => 'test documentation<p><strong>Note:</strong>'
                    . ' This resource supports '
                    . '<a href="https://doc.oroinc.com/api/upsert-operation/" target="_blank">the upsert operation</a>'
                    . ' by the resource identifier.</p>'
            ],
            $this->context->getResult()
        );
    }

    /**
     * @dataProvider upsertTargetActionDataProvider
     */
    public function testPrimaryResourceDocumentationWhenUpsertOperationIsDisabled(string $targetAction): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->setEnabled(false);
        $configObject->getUpsertConfig()->setAllowedById(true);
        $configObject->getUpsertConfig()->addFields(['field1']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }

    public function upsertTargetActionDataProvider(): array
    {
        return [['create'], ['update']];
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByIdAndGroupsOfFieldsForCreate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->setAllowedById(true);
        $configObject->getUpsertConfig()->addFields(['field1']);
        $configObject->getUpsertConfig()->addFields(['field2', 'field3']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('create');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['id'], ['field1'], ['field2', 'field3']],
                'documentation'          => 'test documentation<p><strong>Note:</strong>'
                    . ' This resource supports '
                    . '<a href="https://doc.oroinc.com/api/upsert-operation/" target="_blank">the upsert operation</a>'
                    . ' by the resource identifier'
                    . ' and by the following groups of fields:</p>'
                    . "\n<ul>"
                    . "\n  <li>\"field1\"</li>"
                    . "\n  <li>\"field2\", \"field3\"</li>"
                    . "\n</ul>"
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByIdAndGroupsOfFieldsForUpdate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->setAllowedById(true);
        $configObject->getUpsertConfig()->addFields(['field1']);
        $configObject->getUpsertConfig()->addFields(['field2', 'field3']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('update');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['id'], ['field1'], ['field2', 'field3']],
                'documentation'          => 'test documentation<p><strong>Note:</strong>'
                    . ' This resource supports '
                    . '<a href="https://doc.oroinc.com/api/upsert-operation/" target="_blank">the upsert operation</a>'
                    . ' by the resource identifier.</p>'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByGroupsOfFieldsForCreate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->addFields(['field1']);
        $configObject->getUpsertConfig()->addFields(['field2', 'field3']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('create');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['field1'], ['field2', 'field3']],
                'documentation'          => 'test documentation<p><strong>Note:</strong>'
                    . ' This resource supports '
                    . '<a href="https://doc.oroinc.com/api/upsert-operation/" target="_blank">the upsert operation</a>'
                    . ' by the following groups of fields:</p>'
                    . "\n<ul>"
                    . "\n  <li>\"field1\"</li>"
                    . "\n  <li>\"field2\", \"field3\"</li>"
                    . "\n</ul>"
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByGroupsOfFieldsForUpdate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->addFields(['field1']);
        $configObject->getUpsertConfig()->addFields(['field2', 'field3']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('update');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['field1'], ['field2', 'field3']],
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByFieldsForCreate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->addFields(['field1']);
        $configObject->getUpsertConfig()->addFields(['field2']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('create');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['field1'], ['field2']],
                'documentation'          => 'test documentation<p><strong>Note:</strong>'
                    . ' This resource supports '
                    . '<a href="https://doc.oroinc.com/api/upsert-operation/" target="_blank">the upsert operation</a>'
                    . ' by the following fields:</p>'
                    . "\n<ul>"
                    . "\n  <li>\"field1\"</li>"
                    . "\n  <li>\"field2\"</li>"
                    . "\n</ul>"
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByFieldsForUpdate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->addFields(['field1']);
        $configObject->getUpsertConfig()->addFields(['field2']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('update');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['field1'], ['field2']],
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByOneGroupOfFieldsForCreate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->addFields(['field1', 'field2']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('create');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['field1', 'field2']],
                'documentation'          => 'test documentation<p><strong>Note:</strong>'
                    . ' This resource supports '
                    . '<a href="https://doc.oroinc.com/api/upsert-operation/" target="_blank">the upsert operation</a>'
                    . ' by the combination of "field1" and "field2" fields.</p>'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByOneGroupOfFieldsForUpdate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->addFields(['field1', 'field2']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('update');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['field1', 'field2']],
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByOneFieldForCreate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->addFields(['field1']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('create');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['field1']],
                'documentation'          => 'test documentation<p><strong>Note:</strong>'
                    . ' This resource supports '
                    . '<a href="https://doc.oroinc.com/api/upsert-operation/" target="_blank">the upsert operation</a>'
                    . ' by the "field1" field.</p>'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenUpsertOperationIsAllowedByOneFieldForUpdate(): void
    {
        /** @var EntityDefinitionConfig $configObject */
        $configObject = $this->createConfigObject([
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ]);
        $configObject->getUpsertConfig()->addFields(['field1']);

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setTargetAction('update');
        $this->context->setResult($configObject);
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'upsert'                 => [['field1']],
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }
}
