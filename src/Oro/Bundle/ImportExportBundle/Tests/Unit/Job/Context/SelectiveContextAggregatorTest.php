<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Job\Context;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\BatchBundle\Entity\JobExecution;
use Oro\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\BatchBundle\Item\ExecutionContext;
use Oro\Bundle\ImportExportBundle\Context\Context;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Context\ContextRegistry;
use Oro\Bundle\ImportExportBundle\Job\Context\SelectiveContextAggregator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SelectiveContextAggregatorTest extends TestCase
{
    private ContextRegistry&MockObject $contextRegistry;
    private SelectiveContextAggregator $aggregator;

    #[\Override]
    protected function setUp(): void
    {
        $this->contextRegistry = $this->createMock(ContextRegistry::class);

        $this->aggregator = new SelectiveContextAggregator($this->contextRegistry);
    }

    public function testGetType(): void
    {
        self::assertEquals(SelectiveContextAggregator::TYPE, $this->aggregator->getType());
    }

    public function testGetAggregatedContext(): void
    {
        $execution1Context = new ExecutionContext();
        $execution2Context = new ExecutionContext();
        $execution2Context->put(SelectiveContextAggregator::STEP_PARAMETER_NAME, true);
        $execution3Context = new ExecutionContext();
        $execution3Context->put(SelectiveContextAggregator::STEP_PARAMETER_NAME, false);
        $execution4Context = new ExecutionContext();
        $execution4Context->put(SelectiveContextAggregator::STEP_PARAMETER_NAME, true);

        $stepExecution1 = $this->createMock(StepExecution::class);
        $stepExecution2 = $this->createMock(StepExecution::class);
        $stepExecution3 = $this->createMock(StepExecution::class);
        $stepExecution4 = $this->createMock(StepExecution::class);
        $stepExecutions = new ArrayCollection();
        $stepExecutions->add($stepExecution1);
        $stepExecutions->add($stepExecution2);
        $stepExecutions->add($stepExecution3);
        $stepExecutions->add($stepExecution4);

        $stepExecution1->expects(self::once())
            ->method('getExecutionContext')
            ->willReturn($execution1Context);
        $stepExecution2->expects(self::once())
            ->method('getExecutionContext')
            ->willReturn($execution2Context);
        $stepExecution3->expects(self::once())
            ->method('getExecutionContext')
            ->willReturn($execution3Context);
        $stepExecution4->expects(self::once())
            ->method('getExecutionContext')
            ->willReturn($execution4Context);

        $stepExecution1Context = new Context([]);
        $stepExecution1Context->incrementReadCount();
        $stepExecution2Context = new Context([]);
        $stepExecution2Context->incrementReadCount();
        $stepExecution3Context = new Context([]);
        $stepExecution3Context->incrementReadCount();
        $stepExecution4Context = new Context([]);
        $stepExecution4Context->incrementReadCount();

        $jobExecution = $this->createMock(JobExecution::class);
        $jobExecution->expects(self::once())
            ->method('getStepExecutions')
            ->willReturn($stepExecutions);

        $this->contextRegistry->expects(self::exactly(2))
            ->method('getByStepExecution')
            ->withConsecutive(
                [self::identicalTo($stepExecution2)],
                [self::identicalTo($stepExecution4)]
            )
            ->willReturnOnConsecutiveCalls(
                $stepExecution2Context,
                $stepExecution4Context
            );

        $result = $this->aggregator->getAggregatedContext($jobExecution);
        self::assertInstanceOf(ContextInterface::class, $result);
        self::assertSame(2, $result->getReadCount());
    }

    public function testGetAggregatedContextWhenStepExecutionsAreEmpty(): void
    {
        $stepExecutions = new ArrayCollection();

        $jobExecution = $this->createMock(JobExecution::class);
        $jobExecution->expects(self::once())
            ->method('getStepExecutions')
            ->willReturn($stepExecutions);

        self::assertNull($this->aggregator->getAggregatedContext($jobExecution));
    }
}
