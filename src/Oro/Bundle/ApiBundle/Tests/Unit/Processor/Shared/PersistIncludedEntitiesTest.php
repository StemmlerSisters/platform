<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Shared;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\ApiBundle\Collection\IncludedEntityCollection;
use Oro\Bundle\ApiBundle\Collection\IncludedEntityData;
use Oro\Bundle\ApiBundle\Processor\Shared\PersistIncludedEntities;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\FormProcessorTestCase;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use PHPUnit\Framework\MockObject\MockObject;

class PersistIncludedEntitiesTest extends FormProcessorTestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;
    private PersistIncludedEntities $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);

        $this->processor = new PersistIncludedEntities($this->doctrineHelper);
    }

    public function testProcessWhenIncludedEntitiesCollectionDoesNotExist(): void
    {
        $this->processor->process($this->context);
    }

    public function testProcessWhenIncludedEntitiesCollectionIsEmpty(): void
    {
        $this->context->setIncludedEntities(new IncludedEntityCollection());
        $this->processor->process($this->context);
    }

    public function testProcessForNewIncludedObject(): void
    {
        $object = new \stdClass();
        $objectClass = 'Test\Class';
        $isExistingObject = false;

        $includedEntities = new IncludedEntityCollection();
        $includedEntities->add(
            $object,
            $objectClass,
            'id',
            new IncludedEntityData('/included/0', 0, $isExistingObject)
        );

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManager')
            ->with(self::identicalTo($object), false)
            ->willReturn(null);

        $this->context->setIncludedEntities($includedEntities);
        $this->processor->process($this->context);
    }

    public function testProcessForExistingIncludedObject(): void
    {
        $object = new \stdClass();
        $objectClass = 'Test\Class';
        $isExistingObject = true;

        $includedEntities = new IncludedEntityCollection();
        $includedEntities->add(
            $object,
            $objectClass,
            'id',
            new IncludedEntityData('/included/0', 0, $isExistingObject)
        );

        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->context->setIncludedEntities($includedEntities);
        $this->processor->process($this->context);
    }

    public function testProcessForNewIncludedEntity(): void
    {
        $entity = new \stdClass();
        $entityClass = 'Test\Class';
        $isExistingEntity = false;

        $includedEntities = new IncludedEntityCollection();
        $includedEntities->add(
            $entity,
            $entityClass,
            'id',
            new IncludedEntityData('/included/0', 0, $isExistingEntity)
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManager')
            ->with(self::identicalTo($entity), false)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('persist')
            ->with(self::identicalTo($entity));

        $this->context->setIncludedEntities($includedEntities);
        $this->processor->process($this->context);
    }

    public function testProcessForExistingIncludedEntity(): void
    {
        $entity = new \stdClass();
        $entityClass = 'Test\Class';
        $isExistingEntity = true;

        $includedEntities = new IncludedEntityCollection();
        $includedEntities->add(
            $entity,
            $entityClass,
            'id',
            new IncludedEntityData('/included/0', 0, $isExistingEntity)
        );

        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->context->setIncludedEntities($includedEntities);
        $this->processor->process($this->context);
    }

    public function testProcessWithAdditionalEntitiesToPersist(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        $em = $this->createMock(EntityManagerInterface::class);
        $uow = $this->createMock(UnitOfWork::class);
        $this->doctrineHelper->expects(self::exactly(2))
            ->method('getEntityManager')
            ->withConsecutive(
                [self::identicalTo($entity1), false],
                [self::identicalTo($entity2), false]
            )
            ->willReturn($em);
        $em->expects(self::exactly(2))
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $uow->expects(self::exactly(2))
            ->method('getEntityState')
            ->withConsecutive(
                [self::identicalTo($entity1)],
                [self::identicalTo($entity2)]
            )
            ->willReturnOnConsecutiveCalls(
                UnitOfWork::STATE_NEW,
                UnitOfWork::STATE_MANAGED
            );
        $em->expects(self::once())
            ->method('persist')
            ->with(self::identicalTo($entity1));

        $this->context->addAdditionalEntity($entity1);
        $this->context->addAdditionalEntity($entity2);
        $this->processor->process($this->context);
    }

    public function testProcessWithAdditionalEntitiesToRemove(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        $em = $this->createMock(EntityManagerInterface::class);
        $uow = $this->createMock(UnitOfWork::class);
        $this->doctrineHelper->expects(self::exactly(2))
            ->method('getEntityManager')
            ->withConsecutive(
                [self::identicalTo($entity1), false],
                [self::identicalTo($entity2), false]
            )
            ->willReturn($em);
        $em->expects(self::exactly(2))
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $uow->expects(self::exactly(2))
            ->method('getEntityState')
            ->withConsecutive(
                [self::identicalTo($entity1)],
                [self::identicalTo($entity2)]
            )
            ->willReturnOnConsecutiveCalls(
                UnitOfWork::STATE_NEW,
                UnitOfWork::STATE_MANAGED
            );
        $em->expects(self::once())
            ->method('remove')
            ->with(self::identicalTo($entity2));

        $this->context->addAdditionalEntityToRemove($entity1);
        $this->context->addAdditionalEntityToRemove($entity2);
        $this->processor->process($this->context);
    }
}
