<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Condition;

use Oro\Bundle\WorkflowBundle\Configuration\Checker\ConfigurationChecker;
use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfiguration;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Model\Condition\IsWorkflowConfigurationClean;
use Oro\Component\ConfigExpression\ContextAccessor;
use Oro\Component\ConfigExpression\Exception\InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyPath;

class IsWorkflowConfigurationCleanTest extends TestCase
{
    private ConfigurationChecker&MockObject $checker;
    private IsWorkflowConfigurationClean $condition;

    #[\Override]
    protected function setUp(): void
    {
        $this->checker = $this->createMock(ConfigurationChecker::class);

        $this->condition = new IsWorkflowConfigurationClean($this->checker);
        $this->condition->setContextAccessor(new ContextAccessor());
    }

    public function testGetName(): void
    {
        $this->assertEquals(IsWorkflowConfigurationClean::NAME, $this->condition->getName());
    }

    public function testInitializeFailsWhenEmptyOptions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Options must have 1 element, but 0 given.');

        $this->condition->initialize([]);
    }

    public function testInitializeFailsWhenOptionNotExpressionInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "workflow" is required.');

        $this->condition->initialize([1 => 'anything']);
    }

    public function testEvaluateNotSupported(): void
    {
        $this->checker->expects($this->never())
            ->method($this->anything());

        $this->assertSame($this->condition, $this->condition->initialize([new PropertyPath('data')]));
        $this->assertTrue($this->condition->evaluate(['data' => new \stdClass()]));
    }

    /**
     * @dataProvider evaluateDataProvider
     */
    public function testEvaluate(bool $expected): void
    {
        $configuration = [
            WorkflowConfiguration::NODE_TRANSITIONS => [],
            WorkflowConfiguration::NODE_TRANSITION_DEFINITIONS => []
        ];

        $workflow = new WorkflowDefinition();
        $workflow->setConfiguration($configuration);

        $this->checker->expects($this->once())
            ->method('isClean')
            ->with($configuration)
            ->willReturn($expected);

        $this->assertSame($this->condition, $this->condition->initialize([new PropertyPath('data')]));
        $this->assertEquals($expected, $this->condition->evaluate(['data' => $workflow]));
    }

    public function evaluateDataProvider(): array
    {
        return [
            'clean configuration' => [
                'expected' => true
            ],
            'not clean configuration' => [
                'expected' => false
            ]
        ];
    }
}
