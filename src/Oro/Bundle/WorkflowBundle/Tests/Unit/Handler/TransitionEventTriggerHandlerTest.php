<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Handler;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\WorkflowBundle\Async\TransitionTriggerMessage;
use Oro\Bundle\WorkflowBundle\Configuration\FeatureConfigurationExtension;
use Oro\Bundle\WorkflowBundle\Entity\TransitionEventTrigger;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Handler\TransitionEventTriggerHandler;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransitionEventTriggerHandlerTest extends TestCase
{
    use EntityTrait;

    private const ENTITY_CLASS = 'stdClass';
    private const WORKFLOW_NAME = 'test_workflow';
    private const TRANSITION_NAME = 'test_transition';

    private FeatureChecker&MockObject $featureChecker;
    private WorkflowManager&MockObject $workflowManager;
    private ObjectManager&MockObject $objectManager;
    private TransitionEventTriggerHandler $handler;
    private TransitionEventTrigger $trigger;

    #[\Override]
    protected function setUp(): void
    {
        $this->workflowManager = $this->createMock(WorkflowManager::class);
        $this->objectManager = $this->createMock(ObjectManager::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->any())
            ->method('getManagerForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($this->objectManager);

        $this->featureChecker = $this->createMock(FeatureChecker::class);

        $this->handler = new TransitionEventTriggerHandler($this->workflowManager, $registry, $this->featureChecker);

        $this->trigger = $this->getEntity(
            TransitionEventTrigger::class,
            [
                'transitionName' => self::TRANSITION_NAME,
                'workflowDefinition' => $this->getEntity(
                    WorkflowDefinition::class,
                    [
                        'name' => self::WORKFLOW_NAME,
                        'relatedEntity' => self::ENTITY_CLASS
                    ]
                )
            ]
        );
    }

    public function testProcessWithWorkflowItem(): void
    {
        $entityClass = self::ENTITY_CLASS;
        $entityId = 42;
        $entity = new $entityClass();
        $workflowItem = new WorkflowItem();

        $this->featureChecker->expects($this->once())
            ->method('isResourceEnabled')
            ->with(self::WORKFLOW_NAME, FeatureConfigurationExtension::WORKFLOWS_NODE_NAME)
            ->willReturn(true);

        $this->objectManager->expects($this->once())
            ->method('find')
            ->with(self::ENTITY_CLASS, $entityId)
            ->willReturn($entity);

        $this->workflowManager->expects($this->once())
            ->method('getWorkflowItem')
            ->with($entity, self::WORKFLOW_NAME)
            ->willReturn($workflowItem);
        $this->workflowManager->expects($this->once())
            ->method('transitIfAllowed')
            ->with($workflowItem, self::TRANSITION_NAME)
            ->willReturn(true);

        $this->assertTrue(
            $this->handler->process($this->trigger, TransitionTriggerMessage::create($this->trigger, $entityId))
        );
    }

    public function testProcessWithoutWorkflowItem(): void
    {
        $entityClass = self::ENTITY_CLASS;
        $entityId = 42;
        $entity = new $entityClass();

        $this->featureChecker->expects($this->once())
            ->method('isResourceEnabled')
            ->with(self::WORKFLOW_NAME, FeatureConfigurationExtension::WORKFLOWS_NODE_NAME)
            ->willReturn(true);

        $this->objectManager->expects($this->once())
            ->method('find')
            ->with(self::ENTITY_CLASS, $entityId)
            ->willReturn($entity);

        $this->workflowManager->expects($this->once())
            ->method('getWorkflowItem')
            ->with($entity, self::WORKFLOW_NAME)
            ->willReturn(null);
        $this->workflowManager->expects($this->once())
            ->method('startWorkflow')
            ->with(self::WORKFLOW_NAME, $entity, self::TRANSITION_NAME, [], false)
            ->willReturn(true);

        $this->assertTrue(
            $this->handler->process($this->trigger, TransitionTriggerMessage::create($this->trigger, $entityId))
        );
    }

    /**
     * @dataProvider processExceptionDataProvider
     *
     * @param null|array $entityId
     * @param string $expectedException
     * @param string $expectedMessage
     */
    public function testProcessException($entityId, $expectedException, $expectedMessage): void
    {
        $message = TransitionTriggerMessage::create($this->trigger, $entityId);

        $this->featureChecker->expects($this->once())
            ->method('isResourceEnabled')
            ->with(self::WORKFLOW_NAME, FeatureConfigurationExtension::WORKFLOWS_NODE_NAME)
            ->willReturn(true);

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        $this->handler->process($this->trigger, $message);
    }

    public function testProcessDisabledFeature(): void
    {
        $entityId = 42;

        $this->featureChecker->expects($this->once())
            ->method('isResourceEnabled')
            ->with(self::WORKFLOW_NAME, FeatureConfigurationExtension::WORKFLOWS_NODE_NAME)
            ->willReturn(false);

        $this->objectManager->expects($this->never())
            ->method($this->anything());

        $this->workflowManager->expects($this->never())
            ->method($this->anything());

        $this->assertFalse(
            $this->handler->process($this->trigger, TransitionTriggerMessage::create($this->trigger, $entityId))
        );
    }

    public function processExceptionDataProvider(): array
    {
        $id = ['test' => 1];

        return [
            'empty entity id' => [
                'entityId' => null,
                'expectedException' => \InvalidArgumentException::class,
                'expectedMessage' => sprintf('Message should contain valid %s id', self::ENTITY_CLASS)
            ],
            'without entity' => [
                'data' => 42,
                'expectedException' => EntityNotFoundException::class,
                'expectedMessage' => sprintf('Entity %s with identifier %s not found', self::ENTITY_CLASS, 42)
            ],
            'without entity array key' => [
                'data' => ['test' => 1],
                'expectedException' => EntityNotFoundException::class,
                'expectedMessage' => sprintf(
                    'Entity %s with identifier %s not found',
                    self::ENTITY_CLASS,
                    json_encode($id)
                )
            ]
        ];
    }
}
