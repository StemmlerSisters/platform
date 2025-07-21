<?php

declare(strict_types=1);

namespace Oro\Component\DoctrineUtils\Tests\Unit\ORM;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Oro\Component\DoctrineUtils\ORM\ArrayKeyTrueHydrator;
use PHPUnit\Framework\TestCase;

class ArrayKeyTrueHydratorTest extends TestCase
{
    public function testHydrateAllData(): void
    {
        $stmt = $this->createMock(Result::class);
        $stmt->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('one', 'two', 'three', false);
        $rsm = $this->createMock(ResultSetMapping::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->createMock(Connection::class));
        $entityManager->expects($this->any())
            ->method('getEventManager')
            ->willReturn($this->createMock(EventManager::class));

        $hydrator = new ArrayKeyTrueHydrator($entityManager);

        self::assertSame(['one' => true, 'two' => true, 'three' => true], $hydrator->hydrateAll($stmt, $rsm));
    }
}
