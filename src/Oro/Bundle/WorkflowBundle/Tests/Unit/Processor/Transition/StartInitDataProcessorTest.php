<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Processor\Transition;

use Oro\Bundle\ActionBundle\Button\ButtonSearchContext;
use Oro\Bundle\ActionBundle\Provider\ButtonSearchContextProvider;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Processor\Context\TransitionContext;
use Oro\Bundle\WorkflowBundle\Processor\Transition\StartInitDataProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StartInitDataProcessorTest extends TestCase
{
    private ButtonSearchContextProvider&MockObject $buttonContextProvider;
    private StartInitDataProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->buttonContextProvider = $this->createMock(ButtonSearchContextProvider::class);

        $this->processor = new StartInitDataProcessor($this->buttonContextProvider);
    }

    public function testSkipContextWithoutInitOptions(): void
    {
        $transition = $this->createMock(Transition::class);
        $transition->expects($this->once())
            ->method('isEmptyInitOptions')
            ->willReturn(true);
        $transition->expects($this->never())
            ->method('getInitContextAttribute');

        $context = new TransitionContext();
        $context->setTransition($transition);

        $this->processor->process($context);
    }

    public function addInitContextAttributeToInitData()
    {
        $transition = $this->createMock(Transition::class);
        $transition->expects($this->once())
            ->method('isEmptyInitOptions')
            ->willReturn(false);
        $transition->expects($this->once())
            ->method('getInitContextAttribute')
            ->willReturn('attribute');

        $context = new TransitionContext();
        $context->setTransition($transition);
        $context->set(TransitionContext::INIT_DATA, ['other data' => 42, 'attribute' => 'will be another']);

        $buttonSearchContext = $this->createMock(ButtonSearchContext::class);

        $this->buttonContextProvider->expects($this->once())
            ->method('getButtonSearchContext')
            ->willReturn($buttonSearchContext);

        $this->processor->process($context);

        $this->assertSame(
            [
                'other data' => 42,
                'attribute' => $buttonSearchContext
            ],
            $context->get(TransitionContext::INIT_DATA)
        );

        $this->assertNull($context->get(TransitionContext::ENTITY_ID), 'Entity id must be nulled');
    }
}
