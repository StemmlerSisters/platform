<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Manager;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Entity\Status;
use Oro\Bundle\IntegrationBundle\Manager\DeleteManager;
use Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\TestIntegrationDeleteProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChannelDeleteManagerTest extends TestCase
{
    private Integration $testIntegration;
    private EntityManager&MockObject $em;
    private ClassMetadata&MockObject $entityMetadata;
    private Connection&MockObject $connection;
    private DeleteManager $deleteManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->testIntegration = new Integration();
        $this->testIntegration->setType('test');
        $this->entityMetadata = $this->createMock(ClassMetadata::class);
        $this->em = $this->createMock(EntityManager::class);
        $this->connection = $this->createMock(Connection::class);
        $this->em->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connection);
        $this->connection->expects($this->any())
            ->method('beginTransaction');

        $this->deleteManager = new DeleteManager($this->em);
        $this->deleteManager->addProvider(new TestIntegrationDeleteProvider());
    }

    public function testDeleteChannelWithoutErrors(): void
    {
        $this->entityMetadata->expects(self::once())
            ->method('getTableName')
            ->willReturn('table');
        $this->em->expects(self::once())
            ->method('getClassMetadata')
            ->with(Status::class)
            ->willReturn($this->entityMetadata);
        $this->connection->expects($this->once())
            ->method('commit');
        $this->em->expects($this->any())
            ->method('remove')
            ->with($this->equalTo($this->testIntegration));
        $this->em->expects($this->any())
            ->method('flush');

        $this->assertTrue($this->deleteManager->delete($this->testIntegration));
    }

    public function testDeleteIntegrationWithErrors(): void
    {
        $this->entityMetadata->expects(self::once())
            ->method('getTableName')
            ->willReturn('table');
        $this->em->expects(self::once())
            ->method('getClassMetadata')
            ->with(Status::class)
            ->willReturn($this->entityMetadata);
        $this->em->expects($this->any())
            ->method('remove')
            ->with($this->equalTo($this->testIntegration))
            ->willThrowException(new \Exception());
        $this->connection->expects($this->once())
            ->method('rollback');
        $this->assertFalse($this->deleteManager->delete($this->testIntegration));
    }
}
