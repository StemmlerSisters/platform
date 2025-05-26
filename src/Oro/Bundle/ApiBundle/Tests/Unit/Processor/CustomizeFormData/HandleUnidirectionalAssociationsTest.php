<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\CustomizeFormData;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Form\Handler\UnidirectionalAssociationHandler;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\HandleUnidirectionalAssociations;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDefinition\UnidirectionalAssociationCompleter;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormInterface;

class HandleUnidirectionalAssociationsTest extends CustomizeFormDataProcessorTestCase
{
    private UnidirectionalAssociationHandler&MockObject $handler;
    private HandleUnidirectionalAssociations $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->createMock(UnidirectionalAssociationHandler::class);

        $this->processor = new HandleUnidirectionalAssociations($this->handler);
    }

    public function testProcessWhenNoConfig(): void
    {
        $this->handler->expects(self::never())
            ->method('handleUpdate');

        $this->processor->process($this->context);
    }

    public function testProcessWhenNoUnidirectionalAssociations(): void
    {
        $config = new EntityDefinitionConfig();

        $this->handler->expects(self::never())
            ->method('handleUpdate');

        $this->context->setConfig($config);
        $this->processor->process($this->context);
    }

    public function testProcessWithUnidirectionalAssociations(): void
    {
        $unidirectionalAssociations = ['field1' => 'targetAssociation1'];
        $config = new EntityDefinitionConfig();
        $config->set(UnidirectionalAssociationCompleter::UNIDIRECTIONAL_ASSOCIATIONS, $unidirectionalAssociations);
        $form = $this->createMock(FormInterface::class);

        $this->handler->expects(self::once())
            ->method('handleUpdate')
            ->with(
                self::identicalTo($form),
                self::identicalTo($config),
                $unidirectionalAssociations,
                self::identicalTo($this->context->getRequestType())
            );

        $this->context->setConfig($config);
        $this->context->setForm($form);
        $this->processor->process($this->context);
    }

    public function testProcessWithReadonlyUnidirectionalAssociations(): void
    {
        $config = new EntityDefinitionConfig();
        $config->set(
            UnidirectionalAssociationCompleter::UNIDIRECTIONAL_ASSOCIATIONS,
            ['field1' => 'targetAssociation1', 'field2' => 'targetAssociation2']
        );
        $config->set(UnidirectionalAssociationCompleter::UNIDIRECTIONAL_ASSOCIATIONS_READONLY, ['field1']);
        $form = $this->createMock(FormInterface::class);

        $this->handler->expects(self::once())
            ->method('handleUpdate')
            ->with(
                self::identicalTo($form),
                self::identicalTo($config),
                ['field2' => 'targetAssociation2'],
                self::identicalTo($this->context->getRequestType())
            );

        $this->context->setConfig($config);
        $this->context->setForm($form);
        $this->processor->process($this->context);
    }

    public function testProcessWhenAllUnidirectionalAssociationsReadonly(): void
    {
        $config = new EntityDefinitionConfig();
        $config->set(
            UnidirectionalAssociationCompleter::UNIDIRECTIONAL_ASSOCIATIONS,
            ['field1' => 'targetAssociation1']
        );
        $config->set(UnidirectionalAssociationCompleter::UNIDIRECTIONAL_ASSOCIATIONS_READONLY, ['field1']);
        $form = $this->createMock(FormInterface::class);

        $this->handler->expects(self::never())
            ->method('handleUpdate');

        $this->context->setConfig($config);
        $this->context->setForm($form);
        $this->processor->process($this->context);
    }
}
