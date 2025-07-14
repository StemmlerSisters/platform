<?php

namespace Oro\Bundle\FilterBundle\Tests\Unit\Grid\Extension;

use Oro\Bundle\FilterBundle\Grid\Extension\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->configuration = new Configuration(['string', 'number']);
        $this->processor = new Processor();
    }

    /**
     * @dataProvider processConfigurationDataProvider
     */
    public function testProcessConfiguration(array $configs, array $expected): void
    {
        $this->assertEquals($expected, $this->processor->processConfiguration($this->configuration, $configs));
    }

    /**
     * @dataProvider processInvalidConfigurationStructure
     */
    public function testInvalidConfigurationStructure(array $configs): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->processor->processConfiguration($this->configuration, $configs);
    }

    /**
     * @dataProvider processInvalidConfigurationValues
     */
    public function testInvalidConfigurationValues(array $configs): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->processor->processConfiguration($this->configuration, $configs);
    }

    public function processConfigurationDataProvider(): array
    {
        return [
            'empty' => [
                'configs' => [[]],
                'expected' => [
                    'columns' => [],
                    'default' => [],
                ]
            ],
            'valid' => [
                'configs' => [
                    'filters' => [
                        'columns' => [
                            'sku' => [
                                'type' => 'string',
                                'data_name' => 'test',
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'renderable' => true,
                            'visible' => true,
                            'disabled' => false,
                            'translatable' => true,
                            'force_like' => false,
                            'case_insensitive' => true,
                            'min_length' => 0,
                            'max_length' => PHP_INT_MAX,
                        ],
                    ],
                    'default' => [],
                ],
            ],
            'valid force_like' => [
                'configs' => [
                    'filters' => [
                        'columns' => [
                            'sku' => [
                                'type' => 'string',
                                'data_name' => 'test',
                                'force_like' => true,
                                'min_length' => 3,
                                'max_length' => 99,
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'renderable' => true,
                            'visible' => true,
                            'disabled' => false,
                            'translatable' => true,
                            'force_like' => true,
                            'case_insensitive' => true,
                            'min_length' => 3,
                            'max_length' => 99,
                        ],
                    ],
                    'default' => [],
                ],
            ],
            'valid case sensitive' => [
                'configs' => [
                    'filters' => [
                        'columns' => [
                            'sku' => [
                                'type' => 'string',
                                'data_name' => 'test',
                                'force_like' => true,
                                'case_insensitive' => false,
                                'min_length' => 3,
                                'max_length' => 99,
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'renderable' => true,
                            'visible' => true,
                            'disabled' => false,
                            'translatable' => true,
                            'force_like' => true,
                            'case_insensitive' => false,
                            'min_length' => 3,
                            'max_length' => 99,
                        ],
                    ],
                    'default' => [],
                ],
            ],
            'valid value conversion' => [
                'configs' => [
                    'filters' => [
                        'columns' => [
                            'sku' => [
                                'type' => 'string',
                                'data_name' => 'test',
                                'force_like' => true,
                                'value_conversion' => 'mb_strtoupper',
                                'min_length' => 3,
                                'max_length' => 99,
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'renderable' => true,
                            'visible' => true,
                            'disabled' => false,
                            'translatable' => true,
                            'force_like' => true,
                            'case_insensitive' => true,
                            'value_conversion' => 'mb_strtoupper',
                            'min_length' => 3,
                            'max_length' => 99,
                        ],
                    ],
                    'default' => [],
                ],
            ],
        ];
    }

    public function processInvalidConfigurationStructure(): array
    {
        return [
            ['filters' => ['asd' => 'asdaaa']],
        ];
    }

    public function processInvalidConfigurationValues(): array
    {
        return [
            'invalid filter type' => [[
                'filters' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'asd',
                        ],
                    ],
                ],
            ]],
            'lack of required nodes' => [[
                'filters' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ]],
            'invalid force_like option' => [[
                'filters' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'force_like' => 'string'
                        ],
                    ],
                ],
            ]],
            'invalid min_length option' => [[
                'filters' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'min_length' => 'string'
                        ],
                    ],
                ],
            ]],
            'invalid min_length value' => [[
                'filters' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'min_length' => -1,
                        ],
                    ],
                ],
            ]],
            'invalid max_length option' => [[
                'filters' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'max_length' => 'string'
                        ],
                    ],

                ],
            ]],
            'invalid max_length value' => [[
                'filters' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'max_length' => 0,
                        ],
                    ],
                ],
            ]],
            'invalid `default` type' => [[
                'filters' => [
                    'default' => 123,
                ],
            ]],
            'invalid case insensitive' => [[
                'filters' => [
                    'columns' => [
                        'sku' => [
                            'type' => 'string',
                            'data_name' => 'test',
                            'case_insensitive' => 'string'
                        ],
                    ],
                ],
            ]],
        ];
    }
}
