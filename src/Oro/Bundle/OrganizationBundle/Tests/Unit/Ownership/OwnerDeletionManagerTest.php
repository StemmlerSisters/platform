<?php

namespace Oro\Bundle\OrganizationBundle\Tests\Unit\Ownership;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\OrganizationBundle\Ownership\OwnerAssignmentCheckerInterface;
use Oro\Bundle\OrganizationBundle\Ownership\OwnerDeletionManager;
use Oro\Bundle\OrganizationBundle\Tests\Unit\Ownership\Fixture\Entity\TestEntity;
use Oro\Bundle\OrganizationBundle\Tests\Unit\Ownership\Fixture\Entity\TestOwnerEntity;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadata;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class OwnerDeletionManagerTest extends TestCase
{
    private ContainerInterface&MockObject $checkerContainer;
    private ConfigProvider&MockObject $ownershipProvider;
    private OwnershipMetadataProviderInterface&MockObject $ownershipMetadata;
    private EntityManagerInterface&MockObject $em;
    private OwnerDeletionManager $ownerDeletionManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->checkerContainer = $this->createMock(ContainerInterface::class);
        $this->ownershipProvider = $this->createMock(ConfigProvider::class);
        $this->ownershipMetadata = $this->createMock(OwnershipMetadataProviderInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->ownershipMetadata->expects($this->any())
            ->method('getUserClass')
            ->willReturn(TestOwnerEntity::class);

        $doctrineHelper = $this->createMock(DoctrineHelper::class);
        $doctrineHelper->expects($this->any())
            ->method('getEntityManagerForClass')
            ->willReturn($this->em);

        $this->ownerDeletionManager = new OwnerDeletionManager(
            $this->checkerContainer,
            $this->ownershipProvider,
            $this->ownershipMetadata,
            $doctrineHelper,
            new ObjectIdAccessor($doctrineHelper)
        );
    }

    private function getEntityConfig(string $entityClassName, array $values): Config
    {
        $entityConfigId = new EntityConfigId('entity', $entityClassName);
        $entityConfig = new Config($entityConfigId);
        $entityConfig->setValues($values);

        return $entityConfig;
    }

    public function testIsOwner(): void
    {
        $this->assertTrue($this->ownerDeletionManager->isOwner(new TestOwnerEntity()));
        $this->assertFalse($this->ownerDeletionManager->isOwner(new TestEntity()));
    }

    public function testHasAssignmentsForNotOwnerEntity(): void
    {
        $owner = new TestEntity();

        $this->ownershipProvider->expects($this->never())
            ->method('getConfigs');

        $this->assertFalse($this->ownerDeletionManager->hasAssignments($owner));
    }

    public function testHasAssignmentsForNonOwnerTypeEntity(): void
    {
        $owner = new TestOwnerEntity();

        $entity = new TestEntity();
        $entityClassName = get_class($entity);
        $entityOwnerType = 'NONE';

        $entityConfig = $this->getEntityConfig(
            $entityClassName,
            ['owner_type' => $entityOwnerType]
        );

        $this->ownershipProvider->expects($this->once())
            ->method('getConfigs')
            ->with(null, true)
            ->willReturn([$entityConfig]);
        $this->ownershipMetadata->expects($this->never())
            ->method('getMetadata');

        $this->assertFalse($this->ownerDeletionManager->hasAssignments($owner));
    }

    public function testHasAssignmentsWithDefaultChecker(): void
    {
        $owner = new TestOwnerEntity();
        $ownerId = 123;
        $owner->setId($ownerId);

        $entity = new TestEntity();
        $entityClassName = get_class($entity);
        $entityOwnerType = 'USER';

        $entityConfig = $this->getEntityConfig(
            $entityClassName,
            ['owner_type' => $entityOwnerType]
        );
        $entityOwnershipMetadata = new OwnershipMetadata($entityOwnerType, 'owner', 'owner_id');

        $this->ownershipProvider->expects($this->once())
            ->method('getConfigs')
            ->with(null, true)
            ->willReturn([$entityConfig]);
        $this->ownershipMetadata->expects($this->once())
            ->method('getMetadata')
            ->with($entityClassName)
            ->willReturn($entityOwnershipMetadata);

        $defaultChecker = $this->createMock(OwnerAssignmentCheckerInterface::class);
        $defaultChecker->expects($this->once())
            ->method('hasAssignments')
            ->with($ownerId, $entityClassName, 'owner', $this->identicalTo($this->em))
            ->willReturn(true);
        $this->checkerContainer->expects($this->once())
            ->method('has')
            ->with($entityClassName)
            ->willReturn(false);
        $this->checkerContainer->expects($this->once())
            ->method('get')
            ->with('default')
            ->willReturn($defaultChecker);

        $this->assertTrue($this->ownerDeletionManager->hasAssignments($owner));
    }

    public function testHasAssignmentsWithCustomChecker(): void
    {
        $owner = new TestOwnerEntity();
        $ownerId = 123;
        $owner->setId($ownerId);

        $entity = new TestEntity();
        $entityClassName = get_class($entity);
        $entityOwnerType = 'USER';

        $entityConfig = $this->getEntityConfig(
            $entityClassName,
            ['owner_type' => $entityOwnerType]
        );
        $entityOwnershipMetadata = new OwnershipMetadata($entityOwnerType, 'owner', 'owner_id');

        $this->ownershipProvider->expects($this->once())
            ->method('getConfigs')
            ->with(null, true)
            ->willReturn([$entityConfig]);
        $this->ownershipMetadata->expects($this->once())
            ->method('getMetadata')
            ->with($entityClassName)
            ->willReturn($entityOwnershipMetadata);

        $customChecker = $this->createMock(OwnerAssignmentCheckerInterface::class);
        $customChecker->expects($this->once())
            ->method('hasAssignments')
            ->with($ownerId, $entityClassName, 'owner', $this->identicalTo($this->em))
            ->willReturn(true);
        $this->checkerContainer->expects($this->once())
            ->method('has')
            ->with($entityClassName)
            ->willReturn(true);
        $this->checkerContainer->expects($this->once())
            ->method('get')
            ->with($entityClassName)
            ->willReturn($customChecker);

        $this->assertTrue($this->ownerDeletionManager->hasAssignments($owner));
    }
}
