<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\TransitionTrigger\Verifier;

use Oro\Bundle\WorkflowBundle\Entity\TransitionEventTrigger;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Exception\TransitionTriggerVerifierException;
use Oro\Bundle\WorkflowBundle\Model\TransitionTrigger\Verifier\TransitionEventTriggerExpressionVerifier;
use Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Stub\EntityStub;
use PHPUnit\Framework\TestCase;

class TransitionEventTriggerExpressionVerifierTest extends TestCase
{
    private TransitionEventTriggerExpressionVerifier $verifier;

    #[\Override]
    protected function setUp(): void
    {
        $this->verifier = new TransitionEventTriggerExpressionVerifier();
    }

    /**
     * Covers return statement when trigger without expression comes
     */
    public function testNotVerifyIfNoRequireExpression(): void
    {
        $trigger = new TransitionEventTrigger();

        $this->verifier->verifyTrigger($trigger);
    }

    /**
     * Covers normal configuration processing
     */
    public function testVerificationOk(): void
    {
        $trigger = $this->buildEventTriggerWithExpression(
            'wd.getName() !== wi.getId() and entity.getId() === mainEntity.getId()',
            EntityStub::class,
            EntityStub::class
        );

        $this->verifier->verifyTrigger($trigger);
    }

    /**
     * Covers Expression Language RuntimeException when bad method
     */
    public function testVerificationBadMethodsCallsOk(): void
    {
        $trigger = $this->buildEventTriggerWithExpression(
            'wd.name() !== wi.get() and entity.ping(1) === mainEntity.pong(2)',
            EntityStub::class,
            EntityStub::class
        );

        $this->verifier->verifyTrigger($trigger);
    }

    public function testVerificationBadTypesOperandsOk(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'There is no extended property with the name  in class: Oro\Bundle\WorkflowBundle\Entity\WorkflowItem'
        );
        $trigger = $this->buildEventTriggerWithExpression(
            'wi.get("")[0]',
            EntityStub::class,
            EntityStub::class
        );

        $this->verifier->verifyTrigger($trigger);
    }

    /**
     * @dataProvider verifyFailures
     */
    public function testVerifyTriggerException(string $exceptionMessage, TransitionEventTrigger $trigger): void
    {
        $this->expectException(TransitionTriggerVerifierException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->verifier->verifyTrigger($trigger);
    }

    public function verifyFailures(): array
    {
        return [
            'other' => [
                'Requirement field: "entity.a w < a.b" - syntax error: ' .
                '"Unexpected token "name" of value "w" around position 10 for expression `entity.a w < a.b`."',
                $this->buildEventTriggerWithExpression('entity.a w < a.b', EntityStub::class, EntityStub::class)
            ],
            'variable' => [
                'Requirement field: "e.a < a.b" - syntax error: ' .
                '"Variable "e" is not valid around position 1 for expression `e.a < a.b`. ' .
                'Did you mean "wd"?". ' .
                'Valid context variables are: ' .
                'wd [Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition], ' .
                'wi [Oro\Bundle\WorkflowBundle\Entity\WorkflowItem], ' .
                'entity [Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Stub\EntityStub], ' .
                'mainEntity [Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Stub\EntityStub]',
                $this->buildEventTriggerWithExpression('e.a < a.b', EntityStub::class, EntityStub::class)
            ]
        ];
    }

    private function buildEventTriggerWithExpression(
        ?string $require,
        string $entity,
        string $workflowEntity
    ): TransitionEventTrigger {
        $definition = new WorkflowDefinition();
        $definition->setRelatedEntity($workflowEntity);

        $trigger = new TransitionEventTrigger();
        $trigger->setWorkflowDefinition($definition)->setEntityClass($entity)->setRequire($require);

        return $trigger;
    }
}
