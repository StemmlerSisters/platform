<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Processor\Transition\Layout;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Processor\Context\LayoutDialogResultType;
use Oro\Bundle\WorkflowBundle\Processor\Context\TransitActionResultTypeInterface;
use Oro\Bundle\WorkflowBundle\Processor\Context\TransitionContext;
use Oro\Bundle\WorkflowBundle\Processor\Transition\Layout\LayoutDialogDataTransitionProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class LayoutDialogDataTransitionProcessorTest extends TestCase
{
    private LayoutDialogDataTransitionProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->processor = new LayoutDialogDataTransitionProcessor();
    }

    public function testData(): void
    {
        $transition = $this->createMock(Transition::class);

        $workflowItem = $this->createMock(WorkflowItem::class);
        $workflowItem->expects($this->any())
            ->method('getWorkflowName')
            ->willReturn('test_workflow');

        $formView = $this->createMock(FormView::class);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('createView')
            ->willReturn($formView);

        $context = new TransitionContext();
        $context->setResultType(new LayoutDialogResultType('route_name'));
        $context->setTransition($transition);
        $context->setWorkflowItem($workflowItem);
        $context->setForm($form);

        $this->processor->process($context);

        $this->assertEquals(
            [
                'data' => [
                    'transition' => $transition,
                    'transitionFormView' => $formView,
                    'workflowItem' => $workflowItem,
                    'formRouteName' => 'route_name',
                    'originalUrl' => null
                ]
            ],
            $context->getResult()
        );
        $this->assertTrue($context->isProcessed());
    }

    public function testSkipUnsupportedResultTypeContext(): void
    {
        $context = $this->createMock(TransitionContext::class);
        $context->expects($this->once())
            ->method('getResultType')
            ->willReturn($this->createMock(TransitActionResultTypeInterface::class));

        $context->expects($this->never())
            ->method('getTransition');

        $this->processor->process($context);
    }
}
