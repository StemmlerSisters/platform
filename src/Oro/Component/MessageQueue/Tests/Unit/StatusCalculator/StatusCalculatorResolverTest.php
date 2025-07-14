<?php

namespace Oro\Component\MessageQueue\Tests\Unit\StatusCalculator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Component\MessageQueue\StatusCalculator\CollectionCalculator;
use Oro\Component\MessageQueue\StatusCalculator\QueryCalculator;
use Oro\Component\MessageQueue\StatusCalculator\StatusCalculatorResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatusCalculatorResolverTest extends TestCase
{
    private QueryCalculator&MockObject $queryCalculator;
    private CollectionCalculator&MockObject $collectionCalculator;
    private StatusCalculatorResolver $statusCalculatorResolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->queryCalculator = $this->createMock(QueryCalculator::class);
        $this->collectionCalculator = $this->createMock(CollectionCalculator::class);

        $this->statusCalculatorResolver = new StatusCalculatorResolver(
            $this->collectionCalculator,
            $this->queryCalculator
        );
    }

    public function testGetQueryCalculatorForPersistentCollection(): void
    {
        $childJobCollection = new PersistentCollection(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ClassMetadata::class),
            new ArrayCollection()
        );

        $rootJob = $this->getRootJobWithChildCollection($childJobCollection);
        $calculator = $this->statusCalculatorResolver->getCalculatorForRootJob($rootJob);

        $this->assertSame($this->queryCalculator, $calculator);
    }

    public function testGetCollectionCalculatorForArrayCollection(): void
    {
        $childJobCollection = new ArrayCollection();

        $rootJob = $this->getRootJobWithChildCollection($childJobCollection);
        $calculator = $this->statusCalculatorResolver->getCalculatorForRootJob($rootJob);

        $this->assertSame($this->collectionCalculator, $calculator);
    }

    public function testGetCalculatorForRootJobCollection(): void
    {
        $rootJob = $this->getRootJobWithChildCollection(new ArrayCollection());
        $calculator = $this->statusCalculatorResolver->getCalculatorForRootJob($rootJob);

        $this->assertSame($this->collectionCalculator, $calculator);
    }

    public function testGetCalculatorForRootJobIncorrectType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Can\'t find status and progress calculator for this type of child jobs: "NULL".'
        );

        $rootJob = $this->getRootJobWithChildCollection(null);
        $this->statusCalculatorResolver->getCalculatorForRootJob($rootJob);
    }

    private function getRootJobWithChildCollection(?Collection $childJobCollection): Job
    {
        $rootJob = new Job();
        $rootJob->setChildJobs($childJobCollection);

        return $rootJob;
    }
}
