<?php

namespace Oro\Bundle\DashboardBundle\Tests\Unit\EventListener;

use Oro\Bundle\DashboardBundle\Event\WidgetItemsLoadDataEvent;
use Oro\Bundle\DashboardBundle\EventListener\WidgetItemsLoadDataListener;
use Oro\Bundle\DashboardBundle\Model\WidgetOptionBag;
use PHPUnit\Framework\TestCase;

class WidgetItemsLoadDataListenerTest extends TestCase
{
    private WidgetItemsLoadDataListener $widgetItemsLoadDataListener;

    #[\Override]
    protected function setUp(): void
    {
        $this->widgetItemsLoadDataListener = new WidgetItemsLoadDataListener();
    }

    public function testFilterItemsByItemsChoice(): void
    {
        $expectedItems = [
            'revenue' => [
                'label' => 'Revenue',
            ],
        ];

        $items = [
            'revenue' => [
                'label' => 'Revenue',
            ],
            'orders_number' => [
                'label' => 'Orders number',
            ],
        ];

        $widgetConfig = [
            'configuration' => [
                'subWidgets' => [
                    'type' => 'oro_type_widget_items_choice',
                ],
            ]
        ];

        $options = [
            'subWidgets' => ['revenue']
        ];

        $event = new WidgetItemsLoadDataEvent($items, $widgetConfig, new WidgetOptionBag($options));
        $this->widgetItemsLoadDataListener->filterItemsByItemsChoice($event);
        $this->assertEquals($expectedItems, $event->getItems());
        $this->assertEquals(array_keys($expectedItems), array_keys($event->getItems()));
    }

    /**
     * @dataProvider filterItemsProvider
     */
    public function testFilterItems(array $items, array $config, array $expectedItems): void
    {
        $widgetConfig = [
            'configuration' => [
                'subWidgets' => [],
            ]
        ];

        $event = new WidgetItemsLoadDataEvent($items, $widgetConfig, new WidgetOptionBag(['subWidgets' => $config]));
        $this->widgetItemsLoadDataListener->filterItems($event);
        $this->assertEquals($expectedItems, $event->getItems());
        $this->assertEquals(array_keys($expectedItems), array_keys($event->getItems()));
    }

    public function filterItemsProvider(): array
    {
        return [
            $this->getDataInOrder(),
            $this->getUnsortedData(),
            $this->getUnfilteredData(),
            $this->getMixedData(),
        ];
    }

    private function getDataInOrder(): array
    {
        return [
            'items' => [
                'revenue' => [
                    'label' => 'Revenue',
                ],
                'orders_number' => [
                    'label' => 'Orders number',
                ],
            ],
            'config' => [
                'items' => [
                    [
                        'id'    => 'revenue',
                        'label' => 'Revenue',
                        'show'  => true,
                        'order' => 1,
                    ],
                    [
                        'id'    => 'orders_number',
                        'label' => 'Orders number',
                        'show'  => true,
                        'order' => 2,
                    ],
                ],
            ],
            'expectedItems' => [
                'revenue' => [
                    'label' => 'Revenue',
                ],
                'orders_number' => [
                    'label' => 'Orders number',
                ],
            ],
        ];
    }

    private function getUnsortedData(): array
    {
        return [
            'items' => [
                'revenue' => [
                    'label' => 'Revenue',
                ],
                'orders_number' => [
                    'label' => 'Orders number',
                ],
            ],
            'config' => [
                'items' => [
                    [
                        'id'    => 'revenue',
                        'label' => 'Revenue',
                        'show'  => true,
                        'order' => 2,
                    ],
                    [
                        'id'    => 'orders_number',
                        'label' => 'Orders number',
                        'show'  => true,
                        'order' => 1,
                    ],
                ],
            ],
            'expectedItems' => [
                'orders_number' => [
                    'label' => 'Orders number',
                ],
                'revenue' => [
                    'label' => 'Revenue',
                ],
            ],
        ];
    }

    private function getUnfilteredData(): array
    {
        return [
            'items' => [
                'revenue' => [
                    'label' => 'Revenue',
                ],
                'orders_number' => [
                    'label' => 'Orders number',
                ],
            ],
            'config' => [
                'items' => [
                    [
                        'id'    => 'revenue',
                        'label' => 'Revenue',
                        'show'  => false,
                        'order' => 1,
                    ],
                    [
                        'id'    => 'orders_number',
                        'label' => 'Orders number',
                        'show'  => true,
                        'order' => 2,
                    ],
                ],
            ],
            'expectedItems' => [
                'orders_number' => [
                    'label' => 'Orders number',
                ],
            ],
        ];
    }

    private function getMixedData(): array
    {
        return [
            'items' => [
                'revenue' => [
                    'label' => 'Revenue',
                ],
                'orders_number' => [
                    'label' => 'Orders number',
                ],
                'another' => [
                    'label' => 'Another',
                ],
            ],
            'config' => [
                'items' => [
                    [
                        'id'    => 'revenue',
                        'label' => 'Revenue',
                        'show'  => false,
                        'order' => 3,
                    ],
                    [
                        'id'    => 'orders_number',
                        'label' => 'Orders number',
                        'show'  => true,
                        'order' => 2,
                    ],
                    [
                        'id'    => 'another',
                        'label' => 'Another',
                        'show'  => true,
                        'order' => 1,
                    ],
                ],
            ],
            'expectedItems' => [
                'another' => [
                    'label' => 'Another',
                ],
                'orders_number' => [
                    'label' => 'Orders number',
                ],
            ],
        ];
    }
}
