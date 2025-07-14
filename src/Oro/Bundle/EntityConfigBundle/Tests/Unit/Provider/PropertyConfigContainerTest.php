<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Provider;

use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PropertyConfigContainerTest extends TestCase
{
    private PropertyConfigContainer $configContainer;

    #[\Override]
    protected function setUp(): void
    {
        $this->configContainer = new PropertyConfigContainer([]);
    }

    public function testConfigGetterAndSetter(): void
    {
        $config = ['test' => 'testVal'];

        $this->configContainer->setConfig($config);
        $this->assertEquals($config, $this->configContainer->getConfig());
    }

    public function testGetItemsWithDefaultParams(): void
    {
        $this->configContainer->setConfig(['entity' => ['items' => ['test' => 'testVal']]]);
        $result = $this->configContainer->getItems();

        $this->assertEquals(['test' => 'testVal'], $result);
    }

    /**
     * @dataProvider getItemsProvider
     */
    public function testGetItems(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getItems($type);

        $this->assertEquals($expectedValues, $result);
    }

    /**
     * @dataProvider getRequiredPropertyValuesProvider
     */
    public function testGetRequiredPropertyValues(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getRequiredPropertyValues($type);

        $this->assertEquals($expectedValues, $result);
        // test that a result is cached locally
        $this->assertEquals($expectedValues, $this->configContainer->getRequiredPropertyValues($type));
    }

    /**
     * @dataProvider getNotAuditableValuesProvider
     */
    public function testGetNotAuditableValues(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getNotAuditableValues($type);

        $this->assertEquals($expectedValues, $result);
        // test that a result is cached locally
        $this->assertEquals($expectedValues, $this->configContainer->getNotAuditableValues($type));
    }

    /**
     * @dataProvider getTranslatableValuesProvider
     */
    public function testGetTranslatableValues(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getTranslatableValues($type);

        $this->assertEquals($expectedValues, $result);
        // test that a result is cached locally
        $this->assertEquals($expectedValues, $this->configContainer->getTranslatableValues($type));
    }

    /**
     * @dataProvider getIndexedValuesProvider
     */
    public function testGetIndexedValues(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getIndexedValues($type);

        $this->assertEquals($expectedValues, $result);
        // test that a result is cached locally
        $this->assertEquals($expectedValues, $this->configContainer->getIndexedValues($type));
    }

    /**
     * @dataProvider getFormItemsProvider
     */
    public function testGetFormItems(
        string|EntityConfigId|FieldConfigId $type,
        ?string $fieldType,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getFormItems($type, $fieldType);

        $this->assertEquals($expectedValues, $result);
        // test that a result is cached locally
        $this->assertEquals($expectedValues, $this->configContainer->getFormItems($type, $fieldType));
    }

    /**
     * Cache should independently maintain data for different fieldTypes and all items.
     */
    public function testGetFormItemsCache(): void
    {
        $this->configContainer->setConfig(['field' => $this->getItemsForFormItemsTest()]);
        $result = $this->configContainer->getFormItems(PropertyConfigContainer::TYPE_FIELD, 'string');

        $expectedStringTypeResult = [
            'item1' => [
                'form'    => [
                    'type' => 'SomeForm',
                ],
                'options' => [
                    'allowed_type' => ['string']
                ],
            ],
            'item2' => [
                'form' => [
                    'type' => 'SomeForm',
                ],
            ],
        ];

        $this->assertEquals($expectedStringTypeResult, $result);

        $result = $this->configContainer->getFormItems(PropertyConfigContainer::TYPE_FIELD);

        $expectedNoTypeResult = [
            'item1' => [
                'form'    => [
                    'type' => 'SomeForm',
                ],
                'options' => [
                    'allowed_type' => ['string']
                ],
            ],
            'item2' => [
                'form' => [
                    'type' => 'SomeForm',
                ],
            ],
        ];

        $this->assertEquals($expectedNoTypeResult, $result);
    }

    /**
     * @dataProvider hasFormProvider
     */
    public function testHasForm(
        string|EntityConfigId|FieldConfigId $type,
        ?string $fieldType,
        array $config,
        bool $expectedValue
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->hasForm($type, $fieldType);

        $this->assertEquals($expectedValue, $result);
        // test that a result is cached locally
        $this->assertEquals($expectedValue, $this->configContainer->hasForm($type, $fieldType));
    }

    /**
     * @dataProvider getFormConfigProvider
     */
    public function testGetFormConfig(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getFormConfig($type);

        $this->assertEquals($expectedValues, $result);
    }

    /**
     * @dataProvider getFormBlockConfigProvider
     */
    public function testGetFormBlockConfig(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        ?array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getFormBlockConfig($type);

        $this->assertEquals($expectedValues, $result);
    }

    /**
     * @dataProvider getGridActionsProvider
     */
    public function testGetGridActions(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getGridActions($type);

        $this->assertEquals($expectedValues, $result);
    }

    /**
     * @dataProvider getLayoutActionsProvider
     */
    public function testGetLayoutActions(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getLayoutActions($type);

        $this->assertEquals($expectedValues, $result);
    }

    /**
     * @dataProvider getJsModulesProvider
     */
    public function testGetJsModules(
        string|EntityConfigId|FieldConfigId $type,
        array $config,
        array $expectedValues
    ): void {
        $this->configContainer->setConfig($config);
        $result = $this->configContainer->getJsModules($type);

        $this->assertEquals($expectedValues, $result);
    }

    /**
     * @dataProvider isSchemaUpdateRequiredProvider
     */
    public function testIsSchemaUpdateRequired(string $code, string $type, bool $expected): void
    {
        $config = [
            'entity' => [
                'items' => [
                    'testAttr1' => [
                        'options' => [
                            'require_schema_update' => true
                        ]
                    ],
                    'testAttr2' => [
                        'options' => [
                            'require_schema_update' => false
                        ]
                    ],
                ]
            ],
            'field'  => [
                'items' => [
                    'testAttr1' => [
                        'options' => [
                            'require_schema_update' => true
                        ]
                    ],
                    'testAttr2' => [
                        'options' => [
                            'require_schema_update' => false
                        ]
                    ],
                ]
            ],
        ];
        $this->configContainer->setConfig($config);

        $this->assertEquals(
            $expected,
            $this->configContainer->isSchemaUpdateRequired($code, $type)
        );
    }

    public function getItemsProvider(): array
    {
        return [
            'no entity config'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                [],
            ],
            'entity config'            => [
                PropertyConfigContainer::TYPE_ENTITY,
                ['entity' => ['items' => ['test' => 'testVal']]],
                ['test' => 'testVal']
            ],
            'no field config'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [],
                [],
            ],
            'field config'             => [
                PropertyConfigContainer::TYPE_FIELD,
                ['field' => ['items' => ['test' => 'testFieldVal']]],
                ['test' => 'testFieldVal']
            ],
            'no entity config (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [],
                [],
            ],
            'entity config (by id)'    => [
                new EntityConfigId('testScope', 'Test\Cls'),
                ['entity' => ['items' => ['test' => 'testVal']]],
                ['test' => 'testVal']
            ],
            'no field config (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [],
                [],
            ],
            'field config (by id)'     => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                ['field' => ['items' => ['test' => 'testFieldVal']]],
                ['test' => 'testFieldVal']
            ],
        ];
    }

    public function getRequiredPropertyValuesProvider(): array
    {
        return [
            'no entity config'                     => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                [],
            ],
            'entity config'                        => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => $this->getItemsForRequiredPropertyValuesTest()
                ],
                [
                    'item1' => ['test' => 'testVal'],
                    'item2' => [],
                ]
            ],
            'entity config (by id)'                => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => $this->getItemsForRequiredPropertyValuesTest()
                ],
                [
                    'item1' => ['test' => 'testVal'],
                    'item2' => [],
                ]
            ],
            'field config (no field type)'         => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => $this->getItemsForRequiredPropertyValuesTest()
                ],
                [
                    'item1' => ['test' => 'testVal'],
                    'item2' => [],
                ]
            ],
            'field config (no field type) (by id)' => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => $this->getItemsForRequiredPropertyValuesTest()
                ],
                [
                    'item1' => ['test' => 'testVal'],
                    'item2' => [],
                ]
            ],
        ];
    }

    private function getItemsForRequiredPropertyValuesTest(): array
    {
        return [
            'items' => [
                'item1' => [
                    'options' => [
                        'required_property' => ['test' => 'testVal'],
                    ]
                ],
                'item2' => [
                    'options' => [
                        'required_property' => [],
                    ]
                ],
                'item3' => [
                    'options' => [
                    ]
                ],
            ]
        ];
    }

    public function getNotAuditableValuesProvider(): array
    {
        return [
            'no entity config'      => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                [],
            ],
            'entity config'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => $this->getItemsForNotAuditableValuesTest()
                ],
                [
                    'item2' => true
                ]
            ],
            'entity config (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => $this->getItemsForNotAuditableValuesTest()
                ],
                [
                    'item2' => true
                ]
            ],
            'field config'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => $this->getItemsForNotAuditableValuesTest()
                ],
                [
                    'item2' => true
                ]
            ],
            'field config (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => $this->getItemsForNotAuditableValuesTest()
                ],
                [
                    'item2' => true
                ]
            ],
        ];
    }

    private function getItemsForNotAuditableValuesTest(): array
    {
        return [
            'items' => [
                'item1' => [
                    'options' => [
                        'auditable' => true,
                    ]
                ],
                'item2' => [
                    'options' => [
                        'auditable' => false,
                    ]
                ],
                'item3' => [
                    'options' => [
                    ]
                ],
                'item4' => [
                ],
            ]
        ];
    }

    public function getTranslatableValuesProvider(): array
    {
        return [
            'no entity config'      => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                [],
            ],
            'entity config'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => $this->getItemsForTranslatableValuesTest()
                ],
                ['item1']
            ],
            'entity config (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => $this->getItemsForTranslatableValuesTest()
                ],
                ['item1']
            ],
            'field config'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => $this->getItemsForTranslatableValuesTest()
                ],
                ['item1']
            ],
            'field config (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => $this->getItemsForTranslatableValuesTest()
                ],
                ['item1']
            ],
        ];
    }

    private function getItemsForTranslatableValuesTest(): array
    {
        return [
            'items' => [
                'item1' => [
                    'options' => [
                        'translatable' => true,
                    ]
                ],
                'item2' => [
                    'options' => [
                        'translatable' => false,
                    ]
                ],
                'item3' => [
                    'options' => [
                    ]
                ],
                'item4' => [
                ],
            ]
        ];
    }

    public function getIndexedValuesProvider(): array
    {
        return [
            'no entity config'      => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                [],
            ],
            'entity config'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => $this->getItemsForIndexedValuesTest()
                ],
                [
                    'item1' => true
                ]
            ],
            'entity config (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => $this->getItemsForIndexedValuesTest()
                ],
                [
                    'item1' => true
                ]
            ],
            'field config'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => $this->getItemsForIndexedValuesTest()
                ],
                [
                    'item1' => true
                ]
            ],
            'field config (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => $this->getItemsForIndexedValuesTest()
                ],
                [
                    'item1' => true
                ]
            ],
        ];
    }

    private function getItemsForIndexedValuesTest(): array
    {
        return [
            'items' => [
                'item1' => [
                    'options' => [
                        'indexed' => true,
                    ]
                ],
                'item2' => [
                    'options' => [
                        'indexed' => false,
                    ]
                ],
                'item3' => [
                    'options' => [
                    ]
                ],
                'item4' => [
                ],
            ]
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getFormItemsProvider(): array
    {
        return [
            'no entity config'                        => [
                PropertyConfigContainer::TYPE_ENTITY,
                null,
                [],
                [],
            ],
            'entity config'                           => [
                PropertyConfigContainer::TYPE_ENTITY,
                null,
                [
                    'entity' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item1' => [
                        'form'    => [
                            'type' => 'SomeForm',
                        ],
                        'options' => [
                            'allowed_type' => ['string']
                        ],
                    ],
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
            'entity config (by id)'                   => [
                new EntityConfigId('testScope', 'Test\Cls'),
                null,
                [
                    'entity' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item1' => [
                        'form'    => [
                            'type' => 'SomeForm',
                        ],
                        'options' => [
                            'allowed_type' => ['string']
                        ],
                    ],
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
            'field config (no field type)'            => [
                PropertyConfigContainer::TYPE_FIELD,
                null,
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item1' => [
                        'form'    => [
                            'type' => 'SomeForm',
                        ],
                        'options' => [
                            'allowed_type' => ['string']
                        ],
                    ],
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
            'field config (no field type) (by id)'    => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                null,
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item1' => [
                        'form'    => [
                            'type' => 'SomeForm',
                        ],
                        'options' => [
                            'allowed_type' => ['string']
                        ],
                    ],
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
            'field config'                            => [
                PropertyConfigContainer::TYPE_FIELD,
                'string',
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item1' => [
                        'form'    => [
                            'type' => 'SomeForm',
                        ],
                        'options' => [
                            'allowed_type' => ['string']
                        ],
                    ],
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
            'field config (by id)'                    => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                'string',
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item1' => [
                        'form'    => [
                            'type' => 'SomeForm',
                        ],
                        'options' => [
                            'allowed_type' => ['string']
                        ],
                    ],
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
            'field config (not allowed type)'         => [
                PropertyConfigContainer::TYPE_FIELD,
                'int',
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
            'field config (not allowed type) (by id)' => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                'int',
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
            'entity config1'                           => [
                PropertyConfigContainer::TYPE_ENTITY,
                null,
                [
                    'entity' => $this->getItemsForFormItemsTest()
                ],
                [
                    'item1' => [
                        'form'    => [
                            'type' => 'SomeForm',
                        ],
                        'options' => [
                            'allowed_type' => ['string']
                        ],
                    ],
                    'item2' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ],
                    ],
                ]
            ],
        ];
    }

    private function getItemsForFormItemsTest(): array
    {
        return [
            'items' => [
                'item1' => [
                    'form'    => [
                        'type' => 'SomeForm'
                    ],
                    'options' => [
                        'allowed_type' => ['string']
                    ]
                ],
                'item2' => [
                    'form' => [
                        'type' => 'SomeForm'
                    ],
                ],
                'item3' => [
                    'form' => [
                    ],
                ],
                'item4' => [
                ],
            ]
        ];
    }

    public function hasFormProvider(): array
    {
        return [
            'no entity config'                        => [
                PropertyConfigContainer::TYPE_ENTITY,
                null,
                [],
                false,
            ],
            'entity config (no form)'                 => [
                PropertyConfigContainer::TYPE_ENTITY,
                'int',
                [
                    'entity' => [
                        'item1' => [
                            'form'    => [
                                'type' => 'SomeForm'
                            ],
                            'options' => [
                                'allowed_type' => ['string']
                            ]
                        ],
                        'item2' => [
                            'form' => [
                            ],
                        ],
                        'item3' => [
                        ],
                    ]
                ],
                false
            ],
            'entity config'                           => [
                PropertyConfigContainer::TYPE_ENTITY,
                null,
                [
                    'entity' => $this->getItemsForFormItemsTest()
                ],
                true
            ],
            'entity config (by id)'                   => [
                new EntityConfigId('testScope', 'Test\Cls'),
                null,
                [
                    'entity' => $this->getItemsForFormItemsTest()
                ],
                true
            ],
            'field config (no field type)'            => [
                PropertyConfigContainer::TYPE_FIELD,
                null,
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                true
            ],
            'field config (no field type) (by id)'    => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                null,
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                true
            ],
            'field config'                            => [
                PropertyConfigContainer::TYPE_FIELD,
                'string',
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                true
            ],
            'field config (by id)'                    => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                'string',
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                true
            ],
            'field config (not allowed type)'         => [
                PropertyConfigContainer::TYPE_FIELD,
                'int',
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                true
            ],
            'field config (not allowed type) (by id)' => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                'int',
                [
                    'field' => $this->getItemsForFormItemsTest()
                ],
                true
            ],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getFormConfigProvider(): array
    {
        return [
            'no entity config'                     => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                [],
            ],
            'entity config'                        => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ]
                    ]
                ],
                ['type' => 'SomeForm']
            ],
            'entity config (no form type)'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'form' => [
                        ]
                    ]
                ],
                []
            ],
            'entity config (by id)'                => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ]
                    ]
                ],
                ['type' => 'SomeForm']
            ],
            'entity config (no form type) (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'form' => [
                        ]
                    ]
                ],
                []
            ],
            'field config'                         => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ]
                    ]
                ],
                ['type' => 'SomeForm']
            ],
            'field config (no form type)'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'form' => [
                        ]
                    ]
                ],
                []
            ],
            'field config (by id)'                 => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'form' => [
                            'type' => 'SomeForm',
                        ]
                    ]
                ],
                ['type' => 'SomeForm']
            ],
            'field config (no form type) (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'form' => [
                        ]
                    ]
                ],
                []
            ],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getFormBlockConfigProvider(): array
    {
        return [
            'no entity config'                        => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                null,
            ],
            'entity config'                           => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'form' => [
                            'block_config' => [
                                'test' => 'testVal',
                            ]
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'entity config (no block config)'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'form' => [
                        ]
                    ]
                ],
                null
            ],
            'entity config (by id)'                   => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'form' => [
                            'block_config' => [
                                'test' => 'testVal',
                            ]
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'entity config (no block config) (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'form' => [
                        ]
                    ]
                ],
                null
            ],
            'field config'                            => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'form' => [
                            'block_config' => [
                                'test' => 'testVal',
                            ]
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'field config (no block config)'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'form' => [
                        ]
                    ]
                ],
                null
            ],
            'field config (by id)'                    => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'form' => [
                            'block_config' => [
                                'test' => 'testVal',
                            ]
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'field config (no block config) (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'form' => [
                        ]
                    ]
                ],
                null
            ],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getGridActionsProvider(): array
    {
        return [
            'no entity config'                           => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                [],
            ],
            'entity config'                              => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'grid_action' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'entity config (empty grid actions)'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'grid_action' => [
                        ]
                    ]
                ],
                []
            ],
            'entity config (no grid actions)'            => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                    ]
                ],
                []
            ],
            'entity config (by id)'                      => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'grid_action' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'entity config (empty grid actions) (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'grid_action' => [
                        ]
                    ]
                ],
                []
            ],
            'entity config (no grid actions) (by id)'    => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                    ]
                ],
                []
            ],
            'field config'                               => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'grid_action' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'field config (empty grid actions)'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'grid_action' => [
                        ]
                    ]
                ],
                []
            ],
            'field config (no grid actions)'             => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                    ]
                ],
                []
            ],
            'field config (by id)'                       => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'grid_action' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'field config (empty grid actions) (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'grid_action' => [
                        ]
                    ]
                ],
                []
            ],
            'field config (no grid actions) (by id)'     => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                    ]
                ],
                []
            ],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getLayoutActionsProvider(): array
    {
        return [
            'no entity config'                      => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                []
            ],
            'entity config'                         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'layout_action' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'entity config (empty actions)'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'layout_action' => [
                        ]
                    ]
                ],
                []
            ],
            'entity config (no actions)'            => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                    ]
                ],
                []
            ],
            'entity config (by id)'                 => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'layout_action' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'entity config (empty actions) (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'layout_action' => [
                        ]
                    ]
                ],
                []
            ],
            'entity config (no actions) (by id)'    => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                    ]
                ],
                []
            ],
            'field config'                          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'layout_action' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'field config (empty actions)'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'layout_action' => [
                        ]
                    ]
                ],
                []
            ],
            'field config (no actions)'             => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                    ]
                ],
                []
            ],
            'field config (by id)'                  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'layout_action' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'field config (empty actions) (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'layout_action' => [
                        ]
                    ]
                ],
                []
            ],
            'field config (no actions) (by id)'     => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                    ]
                ],
                []
            ],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getJsModulesProvider(): array
    {
        return [
            'no entity config'                      => [
                PropertyConfigContainer::TYPE_ENTITY,
                [],
                []
            ],
            'entity config'                         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'jsmodules' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'entity config (empty modules)'         => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                        'jsmodules' => [
                        ]
                    ]
                ],
                []
            ],
            'entity config (no modules)'            => [
                PropertyConfigContainer::TYPE_ENTITY,
                [
                    'entity' => [
                    ]
                ],
                []
            ],
            'entity config (by id)'                 => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'jsmodules' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'entity config (empty modules) (by id)' => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                        'jsmodules' => [
                        ]
                    ]
                ],
                []
            ],
            'entity config (no modules) (by id)'    => [
                new EntityConfigId('testScope', 'Test\Cls'),
                [
                    'entity' => [
                    ]
                ],
                []
            ],
            'field config'                          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'jsmodules' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'field config (empty modules)'          => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                        'jsmodules' => [
                        ]
                    ]
                ],
                []
            ],
            'field config (no modules)'             => [
                PropertyConfigContainer::TYPE_FIELD,
                [
                    'field' => [
                    ]
                ],
                []
            ],
            'field config (by id)'                  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'jsmodules' => [
                            'test' => 'testVal',
                        ]
                    ]
                ],
                ['test' => 'testVal']
            ],
            'field config (empty modules) (by id)'  => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                        'jsmodules' => [
                        ]
                    ]
                ],
                []
            ],
            'field config (no modules) (by id)'     => [
                new FieldConfigId('testScope', 'Test\Cls', 'fieldName', 'int'),
                [
                    'field' => [
                    ]
                ],
                []
            ],
        ];
    }

    public function isSchemaUpdateRequiredProvider(): array
    {
        return [
            ['testAttr1', PropertyConfigContainer::TYPE_ENTITY, true],
            ['testAttr2', PropertyConfigContainer::TYPE_ENTITY, false],
            ['testAttr3', PropertyConfigContainer::TYPE_ENTITY, false],
            ['testAttr1', PropertyConfigContainer::TYPE_FIELD, true],
            ['testAttr2', PropertyConfigContainer::TYPE_FIELD, false],
            ['testAttr3', PropertyConfigContainer::TYPE_FIELD, false],
        ];
    }
}
