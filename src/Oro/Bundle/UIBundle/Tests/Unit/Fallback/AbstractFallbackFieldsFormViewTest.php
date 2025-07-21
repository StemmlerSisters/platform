<?php

namespace Oro\Bundle\UIBundle\Tests\Unit\Fallback;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\SecurityBundle\Form\FieldAclHelper;
use Oro\Bundle\UIBundle\Event\BeforeListRenderEvent;
use Oro\Bundle\UIBundle\View\ScrollData;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AbstractFallbackFieldsFormViewTest extends TestCase
{
    protected TranslatorInterface&MockObject $translator;
    protected ManagerRegistry&MockObject $doctrine;
    protected RequestStack&MockObject $requestStack;
    protected BeforeListRenderEvent&MockObject $event;
    protected FieldAclHelper&MockObject $fieldAclHelper;
    protected ScrollData&MockObject $scrollData;
    /** @var FallbackFieldsFormViewStub */
    protected $fallbackFieldsFormView;

    #[\Override]
    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->event = $this->createMock(BeforeListRenderEvent::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->fieldAclHelper = $this->createMock(FieldAclHelper::class);
        $this->scrollData = $this->createMock(ScrollData::class);

        $this->translator->expects($this->any())
            ->method('trans')
            ->willReturnCallback(fn ($id) => $id . '.trans');
        $this->fieldAclHelper->expects($this->any())
            ->method('isFieldAvailable')
            ->willReturn(true);
        $this->fieldAclHelper->expects($this->any())
            ->method('isFieldViewGranted')
            ->willReturn(true);

        $this->fallbackFieldsFormView = new FallbackFieldsFormViewStub(
            $this->requestStack,
            $this->doctrine,
            $this->translator,
            $this->fieldAclHelper
        );
    }

    public function testAddBlockToEntityView(): void
    {
        $env = $this->createMock(Environment::class);
        $env->expects($this->once())
            ->method('render')
            ->willReturn('Rendered template');
        $this->event->expects($this->once())
            ->method('getEnvironment')
            ->willReturn($env);
        $this->scrollData->expects($this->once())
            ->method('addSubBlockData');
        $this->event->expects($this->once())
            ->method('getScrollData')
            ->willReturn($this->scrollData);

        $this->fallbackFieldsFormView->addBlockToEntityView(
            $this->event,
            'fallbackView.html.twig',
            new ProductStub()
        );
    }

    public function testAddBlockToEntityViewWithSectionTitle(): void
    {
        $env = $this->createMock(Environment::class);
        $env->expects($this->once())
            ->method('render')
            ->willReturn('Rendered template');
        $this->event->expects($this->once())
            ->method('getEnvironment')
            ->willReturn($env);
        $this->scrollData->expects($this->once())
            ->method('getData')
            ->willReturn(
                [ScrollData::DATA_BLOCKS => [1 => [ScrollData::TITLE => 'oro.product.sections.inventory.trans']]]
            );
        $this->scrollData->expects($this->once())
            ->method('addSubBlockData');
        $this->event->expects($this->once())
            ->method('getScrollData')
            ->willReturn($this->scrollData);

        $this->fallbackFieldsFormView->addBlockToEntityView(
            $this->event,
            'fallbackView.html.twig',
            new ProductStub(),
            'oro.product.sections.inventory'
        );
    }

    public function testAddBlockToEntityEdit(): void
    {
        $env = $this->createMock(Environment::class);
        $env->expects($this->once())
            ->method('render')
            ->willReturn('Rendered template');
        $this->event->expects($this->once())
            ->method('getEnvironment')
            ->willReturn($env);
        $this->scrollData->expects($this->once())
            ->method('getData')
            ->willReturn(
                ['dataBlocks' => [1 => ['title' => 'oro.catalog.sections.default_options.trans']]]
            );
        $this->scrollData->expects($this->once())
            ->method('addSubBlockData');
        $this->event->expects($this->once())
            ->method('getScrollData')
            ->willReturn($this->scrollData);
        $this->translator->expects($this->any())
            ->method('trans')
            ->willReturnCallback(function ($id) {
                return $id . '.trans';
            });

        $this->fallbackFieldsFormView->addBlockToEntityEdit(
            $this->event,
            'fallbackView.html.twig',
            'oro.catalog.sections.default_options'
        );
    }

    public function testGetEntityFromRequest(): void
    {
        $currentRequest = $this->createMock(Request::class);
        $currentRequest->expects($this->once())
            ->method('get')
            ->with('id')
            ->willReturn(1);
        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($currentRequest);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getReference')
            ->willReturn(ProductStub::class);
        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->with(ProductStub::class)
            ->willReturn($em);

        $this->fallbackFieldsFormView->getEntityFromRequest(ProductStub::class);
    }
}
