<?php

declare(strict_types=1);

namespace Oro\Bundle\DashboardBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Oro\Bundle\DashboardBundle\EventListener\WidgetSortByListener;
use Oro\Bundle\DashboardBundle\Model\WidgetConfigs;
use Oro\Bundle\DashboardBundle\Model\WidgetOptionBag;
use Oro\Bundle\DashboardBundle\Tests\Unit\Fixtures\Entity\TestClass;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Event\OrmResultBeforeQuery;
use Oro\Component\Testing\Unit\ORM\OrmTestCase;

class WidgetSortByListenerTest extends OrmTestCase
{
    /**
     * @dataProvider onResultBeforeQueryShouldNotUpdateQueryProvider
     */
    public function testOnResultBeforeQueryShouldNotUpdateQuery(WidgetOptionBag $widgetOptionBag): void
    {
        $widgetConfigs = $this->createMock(WidgetConfigs::class);
        $widgetConfigs->expects(self::any())
            ->method('getWidgetOptions')
            ->willReturn($widgetOptionBag);

        $em = $this->getTestEntityManager();
        $em->getConfiguration()->setMetadataDriverImpl(new AttributeDriver([]));

        $datagrid = $this->createMock(DatagridInterface::class);

        $qb = $em->createQueryBuilder();
        $originalDQL = $qb->getQuery()->getDQL();

        $widgetSortByListener = new WidgetSortByListener($widgetConfigs);
        $widgetSortByListener->onResultBeforeQuery(new OrmResultBeforeQuery($datagrid, $qb));

        self::assertEquals($originalDQL, $qb->getQuery()->getDQL());
    }

    public function onResultBeforeQueryShouldNotUpdateQueryProvider(): array
    {
        return [
            [new WidgetOptionBag()],
            [new WidgetOptionBag([
                'sortBy' => [
                    'property' => '',
                    'order' => 'ASC',
                    'className' => TestClass::class,
                ],
            ])],
            [new WidgetOptionBag([
                'sortBy' => [
                    'property' => 'nonExisting',
                    'order' => 'ASC',
                    'className' => TestClass::class,
                ]
            ])],
        ];
    }

    /**
     * @dataProvider onResultBeforeQueryShouldUpdateQueryProvider
     */
    public function testOnResultBeforeQueryShouldUpdateQuery(
        WidgetOptionBag $widgetOptionBag,
        string $expectedDQL
    ): void {
        $widgetConfigs = $this->createMock(WidgetConfigs::class);
        $widgetConfigs->expects(self::any())
            ->method('getWidgetOptions')
            ->willReturn($widgetOptionBag);

        $em = $this->getTestEntityManager();
        $em->getConfiguration()->setMetadataDriverImpl(new AttributeDriver([]));
        $qb = $em->createQueryBuilder()
            ->select('tc')
            ->from(TestClass::class, 'tc')
            ->orderBy('tc.id', 'DESC');

        $datagrid = $this->createMock(DatagridInterface::class);

        $widgetSortByListener = new WidgetSortByListener($widgetConfigs);
        $widgetSortByListener->onResultBeforeQuery(new OrmResultBeforeQuery($datagrid, $qb));

        self::assertEquals($expectedDQL, $qb->getQuery()->getDQL());
    }

    public function onResultBeforeQueryShouldUpdateQueryProvider(): array
    {
        return [
            [
                new WidgetOptionBag([
                    'sortBy' => [
                        'property' => 'existing',
                        'order' => 'ASC',
                        'className' => TestClass::class,
                    ]
                ]),
                <<<'DQL'
SELECT tc FROM Oro\Bundle\DashboardBundle\Tests\Unit\Fixtures\Entity\TestClass tc ORDER BY tc.existing ASC
DQL
            ],
        ];
    }
}
