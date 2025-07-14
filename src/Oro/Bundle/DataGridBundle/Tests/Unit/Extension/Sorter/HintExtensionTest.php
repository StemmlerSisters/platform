<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\Extension\Sorter;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Extension\Sorter\HintExtension;
use Oro\Component\DoctrineUtils\ORM\QueryHintResolver;
use PHPUnit\Framework\TestCase;

class HintExtensionTest extends TestCase
{
    private HintExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $queryHintResolver = $this->createMock(QueryHintResolver::class);
        $queryHintResolver->expects(self::any())
            ->method('resolveHintName')
            ->with('HINT_PRECISE_ORDER_BY')
            ->willReturn('oro_entity.precise_order_by');

        $this->extension = new HintExtension($queryHintResolver, 'HINT_PRECISE_ORDER_BY', -261);
        $this->extension->setParameters(new ParameterBag());
    }

    public function testGetPriority(): void
    {
        self::assertSame(-261, $this->extension->getPriority());
    }

    public function testIsApplicableForNotOrmDatasource(): void
    {
        $config = DatagridConfiguration::create(['source' => ['type' => 'other']]);
        self::assertFalse($this->extension->isApplicable($config));
    }

    public function testIsApplicableForOrmDatasource(): void
    {
        $config = DatagridConfiguration::create(['source' => ['type' => 'orm']]);
        self::assertTrue($this->extension->isApplicable($config));
    }

    public function testProcessConfigsWhenNoPreciseOrderByHint(): void
    {
        $config = DatagridConfiguration::create([]);
        $this->extension->processConfigs($config);
        self::assertEquals(
            ['HINT_PRECISE_ORDER_BY'],
            $config->offsetGetByPath('[source][hints]')
        );
    }

    public function testProcessConfigsWhenPreciseOrderByHintAlreadyExists(): void
    {
        $config = DatagridConfiguration::create(['source' => ['hints' => ['HINT_PRECISE_ORDER_BY']]]);
        $this->extension->processConfigs($config);
        self::assertEquals(
            ['HINT_PRECISE_ORDER_BY'],
            $config->offsetGetByPath('[source][hints]')
        );
    }

    public function testProcessConfigsWhenPreciseOrderByHintAlreadyExistsAndWasAddedByItsName(): void
    {
        $config = DatagridConfiguration::create(['source' => ['hints' => ['oro_entity.precise_order_by']]]);
        $this->extension->processConfigs($config);
        self::assertEquals(
            ['oro_entity.precise_order_by'],
            $config->offsetGetByPath('[source][hints]')
        );
    }

    public function testProcessConfigsWhenPreciseOrderByHintAlreadyExistsFullDeclaration(): void
    {
        $config = DatagridConfiguration::create(
            ['source' => ['hints' => [['name' => 'HINT_PRECISE_ORDER_BY', 'value' => true]]]]
        );
        $this->extension->processConfigs($config);
        self::assertEquals(
            [['name' => 'HINT_PRECISE_ORDER_BY', 'value' => true]],
            $config->offsetGetByPath('[source][hints]')
        );
    }

    public function testProcessConfigsWhenPreciseOrderByHintAlreadyExistsFullDeclarationAndWasAddedByItsName(): void
    {
        $config = DatagridConfiguration::create(
            ['source' => ['hints' => [['name' => 'oro_entity.precise_order_by', 'value' => true]]]]
        );
        $this->extension->processConfigs($config);
        self::assertEquals(
            [['name' => 'oro_entity.precise_order_by', 'value' => true]],
            $config->offsetGetByPath('[source][hints]')
        );
    }

    public function testProcessConfigsWhenPreciseOrderByHintDisabled(): void
    {
        $config = DatagridConfiguration::create(
            ['source' => ['hints' => [['name' => 'HINT_PRECISE_ORDER_BY', 'value' => false]]]]
        );
        $this->extension->processConfigs($config);
        self::assertEquals(
            [],
            $config->offsetGetByPath('[source][hints]')
        );
    }

    public function testProcessConfigsWhenPreciseOrderByHintDisabledByItsName(): void
    {
        $config = DatagridConfiguration::create(
            ['source' => ['hints' => [['name' => 'oro_entity.precise_order_by', 'value' => false]]]]
        );
        $this->extension->processConfigs($config);
        self::assertEquals(
            [],
            $config->offsetGetByPath('[source][hints]')
        );
    }
}
