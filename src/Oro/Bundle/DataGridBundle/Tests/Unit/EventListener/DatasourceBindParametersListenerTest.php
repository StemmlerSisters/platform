<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\EventListener;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\DataGridBundle\EventListener\DatasourceBindParametersListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DatasourceBindParametersListenerTest extends TestCase
{
    private BuildAfter&MockObject $event;
    private DatagridInterface&MockObject $datagrid;
    private OrmDatasource&MockObject $datasource;
    private DatasourceBindParametersListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->event = $this->createMock(BuildAfter::class);
        $this->datagrid = $this->createMock(DatagridInterface::class);
        $this->datasource = $this->createMock(OrmDatasource::class);

        $this->listener = new DatasourceBindParametersListener();
    }

    /**
     * @dataProvider onBuildAfterDataProvider
     */
    public function testOnBuildAfterWorks(array $config, ?array $expectedBindParameters = null): void
    {
        $config = DatagridConfiguration::create($config);

        $this->event->expects($this->once())
            ->method('getDatagrid')
            ->willReturn($this->datagrid);

        $this->datagrid->expects($this->once())
            ->method('getDatasource')
            ->willReturn($this->datasource);

        $this->datagrid->expects($this->once())
            ->method('getConfig')
            ->willReturn($config);

        if ($expectedBindParameters) {
            $this->datasource->expects($this->once())
                ->method('bindParameters')
                ->with($expectedBindParameters);
        } else {
            $this->datasource->expects($this->never())
                ->method($this->anything());
        }

        $this->listener->onBuildAfter($this->event);
    }

    public function onBuildAfterDataProvider(): array
    {
        return [
            'applicable config' => [
                'config' => [
                    'source' => [
                        'bind_parameters' => [
                            'foo' => 'bar'
                        ]
                    ],
                ],
                'expectedBindParameters' => ['foo' => 'bar'],
            ],
            'empty bind parameters' => [
                'config' => [
                    'source' => [
                        'bind_parameters' => []
                    ],
                ],
                'expectedBindParameters' => null,
            ],
            'empty option' => [
                'config' => [
                    'source' => [],
                ],
                'expectedBindParameters' => null,
            ],
        ];
    }

    public function testOnBuildAfterWorksSkippedForNotApplicableDatasource(): void
    {
        $this->event->expects($this->once())
            ->method('getDatagrid')
            ->willReturn($this->datagrid);

        $datasource = $this->createMock(DatasourceInterface::class);

        $this->datagrid->expects($this->once())
            ->method('getDatasource')
            ->willReturn($datasource);

        $this->datagrid->expects($this->never())
            ->method('getConfig')
            ->willReturn($datasource);

        $this->datasource->expects($this->never())
            ->method($this->anything());

        $this->listener->onBuildAfter($this->event);
    }
}
