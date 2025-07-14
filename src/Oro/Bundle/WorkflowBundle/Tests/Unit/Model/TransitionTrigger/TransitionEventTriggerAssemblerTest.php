<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\TransitionTrigger;

use Oro\Bundle\WorkflowBundle\Entity\TransitionCronTrigger;
use Oro\Bundle\WorkflowBundle\Entity\TransitionEventTrigger;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Model\TransitionTrigger\TransitionEventTriggerAssembler;
use Oro\Bundle\WorkflowBundle\Model\TransitionTrigger\Verifier\TransitionEventTriggerVerifierInterface;
use Oro\Bundle\WorkflowBundle\Tests\Unit\Model\TransitionTrigger\Stub\AbstractTransitionTriggerAssemblerStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransitionEventTriggerAssemblerTest extends TestCase
{
    private TransitionEventTriggerVerifierInterface&MockObject $verifier;
    private TransitionEventTriggerAssembler $assembler;

    #[\Override]
    protected function setUp(): void
    {
        $this->verifier = $this->createMock(TransitionEventTriggerVerifierInterface::class);
        $this->assembler = new TransitionEventTriggerAssembler($this->verifier);
    }

    /**
     * @dataProvider canAssembleData
     */
    public function testCanAssemble(bool $expected, array $options): void
    {
        $this->assertEquals($expected, $this->assembler->canAssemble($options));
    }

    public function canAssembleData(): array
    {
        return [
            'can' => [
                true,
                [
                    'event' => 'create'
                ]
            ],
            'can not. cron null' => [
                false,
                [
                    'event' => null
                ]
            ],
            'can not: cron not defined' => [
                false,
                [
                    'cron' => '* * * * *'
                ]
            ]
        ];
    }

    public function testAssemble(): void
    {
        $eventOpt = 'update';
        $entityClassOpt = '\EntityClass';
        $fieldOpt = 'field';
        $relationOpt = 'relation';
        $requireOpt = 'expr()';

        $queuedOpt = false;
        $transitionOpt = 'transitionName';
        $workflowDefinitionOpt = new WorkflowDefinition();

        $this->verifier->expects($this->once())
            ->method('verifyTrigger')
            ->with($this->isInstanceOf(TransitionEventTrigger::class));

        /** @var TransitionEventTrigger $trigger */
        $trigger = $this->assembler->assemble(
            [
                'event' => $eventOpt,
                'entity_class' => $entityClassOpt,
                'field' => $fieldOpt,
                'relation' => $relationOpt,
                'require' => $requireOpt,
                'queued' => $queuedOpt
            ],
            $transitionOpt,
            $workflowDefinitionOpt
        );

        $this->assertInstanceOf(
            TransitionEventTrigger::class,
            $trigger,
            'Must return new instance of event trigger entity'
        );

        $this->assertSame($eventOpt, $trigger->getEvent());
        $this->assertSame($entityClassOpt, $trigger->getEntityClass());
        $this->assertSame($fieldOpt, $trigger->getField());
        $this->assertSame($relationOpt, $trigger->getRelation());
        $this->assertSame($requireOpt, $trigger->getRequire());

        $this->assertSame($queuedOpt, $trigger->isQueued());
        $this->assertSame($transitionOpt, $trigger->getTransitionName());
        $this->assertSame($workflowDefinitionOpt, $trigger->getWorkflowDefinition());
    }

    public function testAssembleDefaults(): void
    {
        $eventOpt = 'create';
        $entityClassOpt = '\WorkflowRelatedEntity';
        $fieldOpt = null;
        $relationOpt = null;
        $requireOpt = null;

        $queuedOpt = true;
        $transitionOpt = 'transitionName';
        $workflowDefinitionOpt = new WorkflowDefinition();
        $workflowDefinitionOpt->setRelatedEntity($entityClassOpt);

        $this->verifier->expects($this->once())
            ->method('verifyTrigger')
            ->with($this->isInstanceOf(TransitionEventTrigger::class));

        /** @var TransitionEventTrigger $trigger */
        $trigger = $this->assembler->assemble(
            [
                'event' => $eventOpt,
            ],
            $transitionOpt,
            $workflowDefinitionOpt
        );

        $this->assertInstanceOf(
            TransitionEventTrigger::class,
            $trigger,
            'Must return new instance of event trigger entity'
        );

        $this->assertSame($eventOpt, $trigger->getEvent());
        $this->assertSame($entityClassOpt, $trigger->getEntityClass());
        $this->assertSame($fieldOpt, $trigger->getField());
        $this->assertSame($relationOpt, $trigger->getRelation());
        $this->assertSame($requireOpt, $trigger->getRequire());

        $this->assertSame($queuedOpt, $trigger->isQueued());
        $this->assertSame($transitionOpt, $trigger->getTransitionName());
        $this->assertSame($workflowDefinitionOpt, $trigger->getWorkflowDefinition());
    }

    public function testVerifyTriggerException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Expected instance of Oro\Bundle\WorkflowBundle\Entity\TransitionEventTrigger ' .
            'got Oro\Bundle\WorkflowBundle\Entity\TransitionCronTrigger'
        );

        $stub = new AbstractTransitionTriggerAssemblerStub();

        $stub->verifyProxy($this->assembler, new TransitionCronTrigger());
    }
}
