<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Button;

use Oro\Bundle\ActionBundle\Button\ButtonContext;
use Oro\Bundle\ActionBundle\Button\ButtonInterface;
use Oro\Bundle\ActionBundle\Button\OperationButton;
use Oro\Bundle\ActionBundle\Event\OperationEventDispatcher;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\ActionBundle\Model\Assembler\AttributeAssembler;
use Oro\Bundle\ActionBundle\Model\Assembler\FormOptionsAssembler;
use Oro\Bundle\ActionBundle\Model\Operation;
use Oro\Bundle\ActionBundle\Model\OperationDefinition;
use Oro\Bundle\ActionBundle\Resolver\OptionsResolver;
use Oro\Component\Action\Action\ActionFactoryInterface;
use Oro\Component\ConfigExpression\ExpressionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class OperationButtonTest extends TestCase
{
    private string $originOperationName;
    private Operation $operation;
    private OperationDefinition&MockObject $definition;
    private OperationButton $button;

    #[\Override]
    protected function setUp(): void
    {
        $this->originOperationName = 'origin_name';
        $this->definition = $this->createMock(OperationDefinition::class);
        $this->operation = $this->getOperation($this->definition);

        $this->button = new OperationButton(
            $this->originOperationName,
            $this->operation,
            new ButtonContext(),
            new ActionData()
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals($this->originOperationName, $this->button->getName());
    }

    public function testGetLabel(): void
    {
        $label = 'test_label';
        $this->definition->expects($this->once())
            ->method('getLabel')
            ->willReturn($label);

        $this->assertEquals($label, $this->button->getLabel());
    }

    public function testGetAriaLabel(): void
    {
        $label = 'test_label';
        $this->definition->expects($this->once())
            ->method('getDatagridOptions')
            ->willReturn(['aria_label' => $label]);

        $this->assertEquals($label, $this->button->getAriaLabel());
    }

    public function testGetIcon(): void
    {
        $this->assertNull($this->button->getIcon());

        $icon = 'test-icon';
        $this->definition->expects($this->once())
            ->method('getButtonOptions')
            ->willReturn(['icon' => $icon]);

        $this->assertEquals($icon, $this->button->getIcon());
    }

    public function testGetOrder(): void
    {
        $order = 50;
        $this->definition->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);

        $this->assertEquals($order, $this->button->getOrder());
    }

    public function testGetTemplate(): void
    {
        $this->assertEquals(OperationButton::DEFAULT_TEMPLATE, $this->button->getTemplate());
    }

    public function testGetTemplateData(): void
    {
        $defaultData = [
            'params' => $this->operation->getDefinition(),
            'actionData' => new ActionData(),
            'frontendOptions' => null,
            'buttonOptions' => null,
            'hasForm' => null,
            'showDialog' => false,
            'routeParams' => [
                'operationName' => $this->operation->getDefinition()->getName(),
                'entityClass' => null,
                'entityId' => null,
                'route' => null,
                'datagrid' => null,
                'group' => null,

            ],
            'executionRoute' => null,
            'dialogRoute' => null,
            'additionalData' => [],
            'aClass' => '',
            'jsDialogWidget' => ButtonInterface::DEFAULT_JS_DIALOG_WIDGET,
        ];

        $templateData = $this->button->getTemplateData();
        $this->assertEquals($defaultData, $templateData);
    }

    public function testGetButtonContext(): void
    {
        $this->assertInstanceOf(ButtonContext::class, $this->button->getButtonContext());
    }

    public function testGetTemplateWithConfiguredFrontendOptions(): void
    {
        $templateName = uniqid('test_template', true);
        $this->definition->expects($this->once())
            ->method('getButtonOptions')
            ->willReturn(
                [OperationButton::BUTTON_TEMPLATE_KEY => $templateName]
            );

        $this->assertEquals($templateName, $this->button->getTemplate());
    }

    public function testGetOperation(): void
    {
        $this->assertEquals($this->operation, $this->button->getOperation());
    }

    public function testSetData(): void
    {
        $newData = new ActionData(['test_field' => 'test value']);
        $this->assertNotEquals($newData, $this->button->getTemplateData()['actionData']);
        $this->button->setData($newData);
        $this->assertEquals($newData, $this->button->getTemplateData()['actionData']);
    }

    public function testGetTranslationDomain(): void
    {
        $this->assertNull($this->button->getTranslationDomain());
    }

    public function testClone(): void
    {
        $button = new OperationButton(
            $this->originOperationName,
            $this->operation,
            new ButtonContext(),
            new ActionData()
        );

        $newButton = clone $button;

        $this->assertEquals($newButton->getButtonContext(), $this->button->getButtonContext());
        $this->assertNotSame($newButton->getButtonContext(), $this->button->getButtonContext());

        $this->assertEquals($newButton->getOperation(), $this->button->getOperation());
        $this->assertNotSame($newButton->getOperation(), $this->button->getOperation());
    }

    private function getOperation(OperationDefinition $definition): Operation
    {
        return new Operation(
            $this->createMock(ActionFactoryInterface::class),
            $this->createMock(ExpressionFactory::class),
            $this->createMock(AttributeAssembler::class),
            $this->createMock(FormOptionsAssembler::class),
            $this->createMock(OptionsResolver::class),
            $this->createMock(OperationEventDispatcher::class),
            $definition
        );
    }
}
