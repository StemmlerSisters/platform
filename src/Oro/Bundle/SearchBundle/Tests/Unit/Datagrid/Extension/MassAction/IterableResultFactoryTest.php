<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\Datagrid\Extension\MassAction;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datasource\ArrayDatasource\ArrayDatasource;
use Oro\Bundle\DataGridBundle\Exception\LogicException;
use Oro\Bundle\DataGridBundle\Extension\Action\ActionConfiguration;
use Oro\Bundle\DataGridBundle\Extension\MassAction\DTO\SelectedItems;
use Oro\Bundle\SearchBundle\Datagrid\Datasource\SearchDatasource;
use Oro\Bundle\SearchBundle\Datagrid\Extension\MassAction\IterableResultFactory;
use PHPUnit\Framework\TestCase;

class IterableResultFactoryTest extends TestCase
{
    private IterableResultFactory $iterableResultFactory;

    #[\Override]
    protected function setUp(): void
    {
        $this->iterableResultFactory = new IterableResultFactory();
    }

    public function testIsApplicableWhenNotApplicable(): void
    {
        self::assertFalse($this->iterableResultFactory->isApplicable(new ArrayDatasource()));
    }

    public function testIsApplicable(): void
    {
        $datasource = $this->createMock(SearchDatasource::class);

        self::assertTrue($this->iterableResultFactory->isApplicable($datasource));
    }

    public function testCreateIterableResultWhenDatasourceNotSupported(): void
    {
        $gridConfiguration = $this->createMock(DatagridConfiguration::class);
        $selectedItems = SelectedItems::createFromParameters([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            sprintf('Expecting "%s" datasource type, "%s" given', SearchDatasource::class, ArrayDatasource::class)
        );

        $this->iterableResultFactory->createIterableResult(
            new ArrayDatasource(),
            ActionConfiguration::create([]),
            $gridConfiguration,
            $selectedItems
        );
    }
}
