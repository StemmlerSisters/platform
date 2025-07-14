<?php

namespace Oro\Bundle\EntityPaginationBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityPaginationBundle\EventListener\EntityPaginationListener;
use Oro\Bundle\EntityPaginationBundle\Manager\EntityPaginationManager;
use Oro\Bundle\EntityPaginationBundle\Storage\EntityPaginationStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EntityPaginationListenerTest extends TestCase
{
    private const ENTITY_NAME = 'test_entity';

    private DoctrineHelper&MockObject $doctrineHelper;
    private EntityPaginationStorage&MockObject $storage;
    private EntityPaginationManager&MockObject $paginationManager;
    private EntityPaginationListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->storage = $this->createMock(EntityPaginationStorage::class);
        $this->paginationManager = $this->createMock(EntityPaginationManager::class);

        $this->listener = new EntityPaginationListener($this->doctrineHelper, $this->storage, $this->paginationManager);
    }

    public function testOnResultAfterSystemPaginationDisabled(): void
    {
        $this->paginationManager->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);
        $this->paginationManager->expects($this->never())
            ->method('isDatagridApplicable');

        $this->listener->onResultAfter(new OrmResultAfter($this->createGrid()));
    }

    public function testOnResultAfterGridNotApplicable(): void
    {
        $this->paginationManager->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);
        $this->paginationManager->expects($this->once())
            ->method('isDatagridApplicable')
            ->willReturn(false);
        $this->storage->expects($this->never())
            ->method('clearData');

        $this->listener->onResultAfter(new OrmResultAfter($this->createGrid()));
    }

    public function testOnResultClearData(): void
    {
        $this->paginationManager->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);
        $this->paginationManager->expects($this->once())
            ->method('isDatagridApplicable')
            ->willReturn(true);
        $this->storage->expects($this->once())
            ->method('clearData')
            ->with(self::ENTITY_NAME);

        $this->listener->onResultAfter(new OrmResultAfter($this->createGrid()));
    }

    private function createGrid(): DatagridInterface
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->any())
            ->method('getRootEntities')
            ->willReturn([self::ENTITY_NAME]);

        $this->doctrineHelper->expects($this->any())
            ->method('getEntityMetadata')
            ->with(self::ENTITY_NAME)
            ->willReturn(new ClassMetadata(self::ENTITY_NAME));

        $dataSource = $this->createMock(OrmDatasource::class);
        $dataSource->expects($this->any())
            ->method('getQueryBuilder')
            ->willReturn($queryBuilder);

        $dataGrid = $this->createMock(DatagridInterface::class);
        $dataGrid->expects($this->any())
            ->method('getDatasource')
            ->willReturn($dataSource);

        return $dataGrid;
    }
}
