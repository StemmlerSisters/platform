<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Helper;

use Oro\Bundle\WorkflowBundle\Entity\TransitionEventTrigger;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Helper\TransitionEventTriggerHelper;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransitionEventTriggerHelperTest extends TestCase
{
    private WorkflowManager&MockObject $workflowManager;
    private TransitionEventTrigger $trigger;
    private TransitionEventTriggerHelper $helper;

    #[\Override]
    protected function setUp(): void
    {
        $this->workflowManager = $this->createMock(WorkflowManager::class);

        $this->helper = new TransitionEventTriggerHelper($this->workflowManager);

        $workflowDefinition = new WorkflowDefinition();
        $workflowDefinition->setName('test_workflow')->setRelatedEntity('stdClass');

        $this->trigger = new TransitionEventTrigger();
        $this->trigger->setWorkflowDefinition($workflowDefinition)->setTransitionName('test_transition');
    }

    /**
     * @dataProvider checkRequireDataProvider
     */
    public function testIsRequirePass(string $require, bool $expected): void
    {
        $entity1 = new \stdClass();
        $entity1->testField1 = 'test value 1';

        $prevEntity = new \stdClass();
        $prevEntity->testField = 'test value 1';

        $entity2 = new \stdClass();
        $entity2->testField2 = 'test value 2';
        $entity2->mainEntity = $entity1;

        $this->trigger->setRequire($require)->setRelation('mainEntity');

        $this->assertEquals($expected, $this->helper->isRequirePass($this->trigger, $entity2, $prevEntity));
    }

    public function checkRequireDataProvider(): array
    {
        return [
            'for entity right' => [
                'require' => 'entity.testField2 == "test value 2"',
                'expected' => true,
            ],
            'for entity wrong' => [
                'require' => 'entity.testField2 == "test value 3"',
                'expected' => false,
            ],
            'for previous entity right' => [
                'require' => 'prevEntity.testField == "test value 1"',
                'expected' => true,
            ],
            'for previous entity wrong' => [
                'require' => 'prevEntity.testField == "test value 2"',
                'expected' => false,
            ],
            'for mainEntity right' => [
                'require' => 'mainEntity.testField1 == "test value 1"',
                'expected' => true,
            ],
            'for mainEntity wrong' => [
                'require' => 'mainEntity.testField1 == "test value 3"',
                'expected' => false,
            ],
            'for both right' => [
                'require' => '(mainEntity.testField1 == "test value 1") && (entity.testField2 == "test value 2")',
                'expected' => true,
            ],
            'for both wrong' => [
                'require' => '(mainEntity.testField1 == "test value 3") && (entity.testField2 == "test value 2")',
                'expected' => false,
            ],
        ];
    }

    public function testIsRequirePassWrongEntity(): void
    {
        $entity = new \stdClass();
        $entity->testField2 = 'test value';
        $entity->mainEntity = null;

        $this->trigger->setRequire('mainEntity.field')->setRelation('mainEntity');

        $this->assertFalse($this->helper->isRequirePass($this->trigger, $entity, new \stdClass()));
    }

    public function testGetMainEntityWrongEntity(): void
    {
        $entity = new \stdClass();
        $entity->testField2 = 'test value 2';
        $entity->mainEntity = null;

        $this->trigger
            ->setRequire('testField2 == "test value 2"')
            ->setRelation('mainEntity');

        $this->assertNull($this->helper->getMainEntity($this->trigger, $entity));
    }

    /**
     * @dataProvider buildContextValuesProvider
     */
    public function testBuildContextValues(array $expected, array $arguments): void
    {
        $this->assertSame(
            $expected,
            call_user_func_array([$this->helper, 'buildContextValues'], $arguments)
        );
    }

    public function buildContextValuesProvider(): array
    {
        $item = new WorkflowItem();
        $definition = new WorkflowDefinition();
        $triggerEntity = new \stdClass();
        $workflowEntity = new \stdClass();
        $prevEntity = new \stdClass();

        return [
            'emptyness' => [
                [
                    TransitionEventTriggerHelper::TRIGGER_WORKFLOW_DEFINITION => null,
                    TransitionEventTriggerHelper::TRIGGER_WORKFLOW_ITEM => null,
                    TransitionEventTriggerHelper::TRIGGER_ENTITY => null,
                    TransitionEventTriggerHelper::TRIGGER_WORKFLOW_ENTITY => null,
                    TransitionEventTriggerHelper::TRIGGER_PREVIOUS_ENTITY => null,
                ],
                [
                    null,
                    null,
                    null,
                    null,
                    null,
                ]
            ],
            'types' => [
                [
                    TransitionEventTriggerHelper::TRIGGER_WORKFLOW_DEFINITION => $definition,
                    TransitionEventTriggerHelper::TRIGGER_WORKFLOW_ITEM => $item,
                    TransitionEventTriggerHelper::TRIGGER_ENTITY => $triggerEntity,
                    TransitionEventTriggerHelper::TRIGGER_WORKFLOW_ENTITY => $workflowEntity,
                    TransitionEventTriggerHelper::TRIGGER_PREVIOUS_ENTITY => $prevEntity,
                ],
                [
                    $definition, $triggerEntity, $workflowEntity, $item, $prevEntity
                ]
            ]
        ];
    }
}
