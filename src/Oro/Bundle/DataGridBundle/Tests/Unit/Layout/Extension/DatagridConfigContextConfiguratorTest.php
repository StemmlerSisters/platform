<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\Layout\Extension;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\ManagerInterface;
use Oro\Bundle\DataGridBundle\Layout\Extension\DatagridConfigContextConfigurator;
use Oro\Component\Layout\LayoutContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DatagridConfigContextConfiguratorTest extends TestCase
{
    private ManagerInterface&MockObject $dataGridManager;
    private DatagridConfigContextConfigurator $contextConfigurator;

    #[\Override]
    protected function setUp(): void
    {
        $this->dataGridManager = $this->createMock(ManagerInterface::class);

        $this->contextConfigurator = new DatagridConfigContextConfigurator($this->dataGridManager);
    }

    public function testConfigureContext(): void
    {
        $context = new LayoutContext();
        $context['grid_config'] = ['grid_name'];

        $config = $this->createMock(DatagridConfiguration::class);
        $config->expects($this->once())
            ->method('toArray')
            ->willReturn(['config']);

        $this->dataGridManager->expects($this->once())
            ->method('getConfigurationForGrid')
            ->with('grid_name')
            ->willReturn($config);

        $this->contextConfigurator->configureContext($context);
        $context->resolve();

        $this->assertTrue($context->has('grid_config'));
        $this->assertEquals(['grid_name' => ['config']], $context->get('grid_config'));
    }

    public function testConfigureContextEmptyGridConfig(): void
    {
        $context = new LayoutContext();
        $context['grid_config'] = [];
        $this->contextConfigurator->configureContext($context);
        $context->resolve();

        $this->dataGridManager->expects($this->never())
            ->method('getConfigurationForGrid');

        $this->assertEquals([], $context->get('grid_config'));
    }

    public function testConfigureContextNoGridConfig(): void
    {
        $context = new LayoutContext();
        $this->contextConfigurator->configureContext($context);
        $context->resolve();

        $this->assertFalse($context->has('grid_config'));
    }

    public function testConfigureContextIfGridConfigNotArray(): void
    {
        $context = new LayoutContext();
        $context['grid_config'] = 123;
        $this->contextConfigurator->configureContext($context);

        $this->expectException(\LogicException::class);
        $expectedMessage = 'Failed to resolve the context variables. Reason: The option "grid_config" with value 123';
        $this->expectExceptionMessage($expectedMessage);

        $context->resolve();
    }

    public function testConfigureContextIfInvalidDataArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "grid_config" value must be a string, but "array" given.');

        $context = new LayoutContext();
        $context['grid_config'] = [['grid_name']];
        $this->contextConfigurator->configureContext($context);
    }
}
