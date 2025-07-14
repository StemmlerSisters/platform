<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\EventListener;

use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\EventListener\AttributeFormViewListener;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\Stub\AttributeGroupStub;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\SecurityBundle\Form\FieldAclHelper;
use Oro\Bundle\TestFrameworkBundle\Entity\TestActivityTarget;
use Oro\Bundle\UIBundle\Event\BeforeListRenderEvent;
use Oro\Bundle\UIBundle\View\ScrollData;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;
use Twig\Environment;

class AttributeFormViewListenerTest extends TestCase
{
    use EntityTrait;

    private Environment&MockObject $environment;
    private AttributeManager&MockObject $attributeManager;
    private FieldAclHelper&MockObject $fieldAclHelper;
    private AttributeFormViewListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->environment = $this->createMock(Environment::class);
        $this->attributeManager = $this->createMock(AttributeManager::class);
        $this->fieldAclHelper = $this->createMock(FieldAclHelper::class);
        $this->fieldAclHelper->expects($this->any())
            ->method('isFieldAvailable')
            ->willReturn(true);
        $this->fieldAclHelper->expects($this->any())
            ->method('isFieldViewGranted')
            ->willReturn(true);

        $this->listener = new AttributeFormViewListener(
            $this->attributeManager,
            $this->fieldAclHelper
        );
    }

    public function testOnEditWithoutFormRenderEvent(): void
    {
        $this->attributeManager->expects($this->never())
            ->method('getGroupsWithAttributes');

        $this->listener->onEdit(new BeforeListRenderEvent($this->environment, new ScrollData(), new \stdClass()));
    }

    public function testOnViewWithoutViewRenderEvent(): void
    {
        $this->attributeManager->expects($this->never())
            ->method('getGroupsWithAttributes');

        $this->listener->onViewList(new BeforeListRenderEvent($this->environment, new ScrollData(), new \stdClass()));
    }

    /**
     * @dataProvider formRenderDataProvider
     */
    public function testFormRender(
        array $groupsData,
        array $scrollData,
        string $templateHtml,
        array $expectedData,
        array $formViewChildren
    ): void {
        $formView = new FormView();
        $formView->children = $formViewChildren;

        $entity = $this->getEntity(TestActivityTarget::class, [
            'attributeFamily' => $this->getEntity(AttributeFamily::class)
        ]);

        $this->environment->expects($templateHtml ? $this->once() : $this->never())
            ->method('render')
            ->willReturn($templateHtml);

        $this->attributeManager->expects($this->once())
            ->method('getGroupsWithAttributes')
            ->willReturn($groupsData);

        $scrollData = new ScrollData($scrollData);
        $listEvent = new BeforeListRenderEvent($this->environment, $scrollData, $entity, $formView);
        $this->listener->onEdit($listEvent);

        $this->assertEquals($expectedData, $listEvent->getScrollData()->getData());
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function formRenderDataProvider(): array
    {
        $label = $this->getEntity(LocalizedFallbackValue::class, ['string' => 'Group1Title']);
        $group1 = $this->getEntity(AttributeGroupStub::class, ['code' => 'group1', 'label' => $label]);
        $attributeVisible = $this->getEntity(
            FieldConfigModel::class,
            [
                'id' => 1,
                'fieldName' => 'someField',
                'data' => [
                    'view' => ['is_displayable' => true],
                    'form' => ['is_enabled' => true]
                ]
            ]
        );
        $attributeInvisible = $this->getEntity(
            FieldConfigModel::class,
            [
                'id' => 1,
                'fieldName' => 'someField',
                'data' => [
                    'view' => ['is_displayable' => false],
                    'form' => ['is_enabled' => false]
                ]
            ]
        );

        return [
            'empty group not added' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => []]
                ],
                'scrollData' => [],
                'templateHtml' => '',
                'expectedData' => [],
                'formViewChildren' => [],
            ],
            'empty group gets deleted' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => []]
                ],
                'scrollData' => [
                    ScrollData::DATA_BLOCKS => [
                        'group1' => []
                    ]
                ],
                'templateHtml' => '',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                    ]
                ],
                'formViewChildren' => [],
            ],
            'new group is added' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => [$attributeVisible]]
                ],
                'scrollData' => [],
                'templateHtml' => 'field template',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => ['someField' => 'field template']
                                ]
                            ]
                        ]
                    ]
                ],
                'formViewChildren' => ['someField' => new FormView()],
            ],
            'attributes are added to the last subblock' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => [$attributeVisible]],
                ],
                'scrollData' => [
                    ScrollData::DATA_BLOCKS => [

                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                ['data' => ['alreadyExistingField1' => 'sample data1']],
                                ['data' => ['alreadyExistingField2' => 'sample data2']],
                            ],
                        ],
                    ],
                ],
                'templateHtml' => 'field template',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => [
                                        'alreadyExistingField1' => 'sample data1',
                                    ],
                                ],
                                [
                                    'data' => [
                                        'alreadyExistingField2' => 'sample data2',
                                        'someField' => 'field template',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'formViewChildren' => [
                    'alreadyExistingField' => (new FormView())->setRendered(),
                    'someField' => new FormView(),
                ],
            ],
            'invisible attribute not displayed' => [
                'groupsData' => [
                    [
                        'group' => $group1,
                        'attributes' => [
                            $attributeVisible,
                            $attributeInvisible
                        ]
                    ]
                ],
                'scrollData' => [],
                'templateHtml' => 'field template',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => ['someField' => 'field template']
                                ]
                            ]
                        ]
                    ]
                ],
                'formViewChildren' => ['someField' => new FormView()],
            ],
            'move attribute field to other group' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => [$attributeVisible]]
                ],
                'scrollData' => [
                    ScrollData::DATA_BLOCKS => [
                        'existingGroup' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => [
                                        'someField' => 'field template',
                                        'otherField' => 'field template'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'templateHtml' => '',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                        'existingGroup' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => [
                                        'otherField' => 'field template'
                                    ]
                                ]
                            ]
                        ],
                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => ['someField' => 'field template']
                                ]
                            ]
                        ]
                    ]
                ],
                'formViewChildren' => ['someField' => (new FormView())->setRendered()],
            ]
        ];
    }

    /**
     * @dataProvider viewListDataProvider
     */
    public function testViewList(
        array $groupsData,
        array $scrollData,
        string $templateHtml,
        array $expectedData
    ): void {
        $entity = $this->getEntity(TestActivityTarget::class, [
            'attributeFamily' => $this->getEntity(AttributeFamily::class)
        ]);

        $this->environment->expects($this->exactly((int)!empty($templateHtml)))
            ->method('render')
            ->willReturn($templateHtml);

        $this->attributeManager->expects($this->once())
            ->method('getGroupsWithAttributes')
            ->willReturn($groupsData);

        $scrollData = new ScrollData($scrollData);
        $listEvent = new BeforeListRenderEvent($this->environment, $scrollData, $entity);
        $this->listener->onViewList($listEvent);

        $this->assertEquals($expectedData, $listEvent->getScrollData()->getData());
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function viewListDataProvider(): array
    {
        $label = $this->getEntity(LocalizedFallbackValue::class, ['string' => 'Group1Title']);
        $group1 = $this->getEntity(AttributeGroupStub::class, ['code' => 'group1', 'label' => $label]);
        $attributeVisible = $this->getEntity(
            FieldConfigModel::class,
            [
                'id' => 1,
                'fieldName' => 'someField',
                'data' => [
                    'view' => ['is_displayable' => true],
                    'form' => ['is_enabled' => true]
                ]
            ]
        );
        $attributeInvisible = $this->getEntity(
            FieldConfigModel::class,
            [
                'id' => 1,
                'fieldName' => 'someField',
                'data' => [
                    'view' => ['is_displayable' => false],
                    'form' => ['is_enabled' => false]
                ]
            ]
        );

        return [
            'empty group not added' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => []]
                ],
                'scrollData' => [],
                'templateHtml' => '',
                'expectedData' => [],
            ],
            'empty group gets deleted' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => []]
                ],
                'scrollData' => [
                    ScrollData::DATA_BLOCKS => [
                        'group1' => []
                    ]
                ],
                'templateHtml' => '',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                    ]
                ],
            ],
            'new group is added' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => [$attributeVisible]]
                ],
                'scrollData' => [],
                'templateHtml' => 'field template',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => ['someField' => 'field template']
                                ]
                            ]
                        ]
                    ]
                ],
            ],
            'attributes are added to the last subblock' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => [$attributeVisible]]
                ],
                'scrollData' => [
                    ScrollData::DATA_BLOCKS => [

                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                ['data' => ['alreadyExistingField1' => 'sample data1']],
                                ['data' => ['alreadyExistingField2' => 'sample data2']],
                            ],
                        ],
                    ],
                ],
                'templateHtml' => 'field template',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => ['alreadyExistingField1' => 'sample data1']
                                ],
                                [
                                    'data' => [
                                        'alreadyExistingField2' => 'sample data2',
                                        'someField' => 'field template',
                                    ],
                                ],
                            ]
                        ]
                    ]
                ],
            ],
            'invisible attribute not displayed' => [
                'groupsData' => [
                    [
                        'group' => $group1,
                        'attributes' => [
                            $attributeVisible,
                            $attributeInvisible
                        ]
                    ]
                ],
                'scrollData' => [],
                'templateHtml' => 'field template',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => ['someField' => 'field template']
                                ]
                            ]
                        ]
                    ]
                ],
            ],
            'move attribute field to other group' => [
                'groupsData' => [
                    ['group' => $group1, 'attributes' => [$attributeVisible]]
                ],
                'scrollData' => [
                    ScrollData::DATA_BLOCKS => [
                        'existingGroup' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => [
                                        'someField' => 'field template',
                                        'otherField' => 'field template'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'templateHtml' => '',
                'expectedData' => [
                    ScrollData::DATA_BLOCKS => [
                        'existingGroup' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => [
                                        'otherField' => 'field template'
                                    ]
                                ]
                            ]
                        ],
                        'group1' => [
                            'title' => 'Group1Title',
                            'useSubBlockDivider' => true,
                            'subblocks' => [
                                [
                                    'data' => ['someField' => 'field template']
                                ]
                            ]
                        ]
                    ]
                ],
            ]
        ];
    }
}
