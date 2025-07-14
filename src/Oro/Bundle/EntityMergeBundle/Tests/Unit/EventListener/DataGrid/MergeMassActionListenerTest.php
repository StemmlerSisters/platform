<?php

namespace Oro\Bundle\EntityMergeBundle\Tests\Unit\EventListener\DataGrid;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\EntityConfigBundle\Config\Config as EntityConfig;
use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityMergeBundle\EventListener\DataGrid\MergeMassActionListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MergeMassActionListenerTest extends TestCase
{
    private ConfigProvider&MockObject $entityConfigProvider;
    private MergeMassActionListener $mergeMassActionListener;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityConfigProvider = $this->createMock(ConfigProvider::class);

        $this->mergeMassActionListener = new MergeMassActionListener($this->entityConfigProvider);
    }

    public function testOnBuildUnsetMergeMassAction(): void
    {
        $entityName = 'testEntityName';
        $datagridConfig = DatagridConfiguration::create(
            ['mass_actions' => ['merge' => ['entity_name' => $entityName]]]
        );
        $entityConfig = $this->getEntityConfig();

        $this->entityConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($entityName)
            ->willReturn($entityConfig);

        $event = $this->getBuildBeforeEvent($datagridConfig);
        $this->mergeMassActionListener->onBuildBefore($event);

        $this->assertEquals(
            ['mass_actions' => []],
            $datagridConfig->toArray()
        );
    }

    public function testOnBuildNotUnsetMergeMass(): void
    {
        $entityName = 'testEntityName';
        $datagridConfig = DatagridConfiguration::create(
            ['mass_actions' => ['merge' => ['entity_name' => $entityName]]]
        );
        $entityConfig = $this->getEntityConfig(['enable' => true]);

        $this->entityConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($entityName)
            ->willReturn($entityConfig);

        $event = $this->getBuildBeforeEvent($datagridConfig);
        $this->mergeMassActionListener->onBuildBefore($event);

        $this->assertEquals(
            ['mass_actions' => ['merge' => ['entity_name' => $entityName]]],
            $datagridConfig->toArray()
        );
    }

    public function testOnBuildBeforeSkipsForEmptyMassActions(): void
    {
        $datagridConfig = DatagridConfiguration::create(
            ['mass_actions' => []]
        );

        $this->entityConfigProvider->expects($this->never())
            ->method('getConfig');

        $event = $this->getBuildBeforeEvent($datagridConfig);
        $this->mergeMassActionListener->onBuildBefore($event);
    }

    public function testOnBuildBeforeForEmptyEntityName(): void
    {
        $datagridConfig = DatagridConfiguration::create(
            ['mass_actions' => ['merge' => ['entity_name' => '']]]
        );

        $this->entityConfigProvider->expects($this->never())
            ->method('getConfig');

        $event = $this->getBuildBeforeEvent($datagridConfig);
        $this->mergeMassActionListener->onBuildBefore($event);
    }

    private function getBuildBeforeEvent(DatagridConfiguration $datagridConfig): BuildBefore
    {
        return new BuildBefore(
            $this->createMock(DatagridInterface::class),
            $datagridConfig
        );
    }

    private function getEntityConfig(array $values = []): EntityConfig
    {
        return new EntityConfig(
            $this->createMock(ConfigIdInterface::class),
            $values
        );
    }
}
