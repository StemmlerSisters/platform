<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Create;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Processor\Create\CreateEntity;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\ApiBundle\Util\EntityInstantiator;
use Oro\Bundle\ApiBundle\Util\EntityLoader;
use PHPUnit\Framework\MockObject\MockObject;

class CreateEntityTest extends CreateProcessorTestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;
    private EntityLoader&MockObject $entityLoader;
    private EntityInstantiator&MockObject $entityInstantiator;
    private CreateEntity $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->entityLoader = $this->createMock(EntityLoader::class);
        $this->entityInstantiator = $this->createMock(EntityInstantiator::class);

        $this->processor = new CreateEntity(
            $this->doctrineHelper,
            $this->entityLoader,
            $this->entityInstantiator
        );
    }

    public function testProcessWithoutEntityId(): void
    {
        $entityClass = Entity\Product::class;
        $entity = new $entityClass();
        $config = new EntityDefinitionConfig();

        $this->entityInstantiator->expects(self::once())
            ->method('instantiate')
            ->with($entityClass)
            ->willReturn($entity);

        $this->context->setClassName($entityClass);
        $this->context->setConfig($config);
        $this->processor->process($this->context);

        self::assertSame($entity, $this->context->getResult());
    }

    public function testProcessForNotManageableEntity(): void
    {
        $entityClass = Entity\Product::class;
        $entity = new $entityClass();
        $config = new EntityDefinitionConfig();

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('isManageableEntityClass')
            ->with($entityClass)
            ->willReturn(false);
        $this->entityInstantiator->expects(self::once())
            ->method('instantiate')
            ->with($entityClass)
            ->willReturn($entity);

        $this->context->setClassName($entityClass);
        $this->context->setId(123);
        $this->context->setConfig($config);
        $this->processor->process($this->context);

        self::assertSame($entity, $this->context->getResult());
    }

    public function testProcessForEntityWithIdGenerator(): void
    {
        $entityClass = Entity\Product::class;
        $entity = new $entityClass();
        $config = new EntityDefinitionConfig();
        $metadata = new EntityMetadata($entityClass);
        $metadata->setHasIdentifierGenerator(true);

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('isManageableEntityClass')
            ->with($entityClass)
            ->willReturn(true);
        $this->entityInstantiator->expects(self::once())
            ->method('instantiate')
            ->with($entityClass)
            ->willReturn($entity);

        $this->context->setClassName($entityClass);
        $this->context->setId(123);
        $this->context->setConfig($config);
        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame($entity, $this->context->getResult());
    }

    public function testProcessForEntityWithoutIdGeneratorAndEntityDoesNotExist(): void
    {
        $entityClass = Entity\Product::class;
        $entityId = 123;
        $entity = new $entityClass();
        $config = new EntityDefinitionConfig();
        $metadata = new EntityMetadata($entityClass);
        $metadata->setHasIdentifierGenerator(false);

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('isManageableEntityClass')
            ->with($entityClass)
            ->willReturn(true);
        $this->entityLoader->expects(self::once())
            ->method('findEntity')
            ->with($entityClass, $entityId, self::identicalTo($metadata))
            ->willReturn(null);
        $this->entityInstantiator->expects(self::once())
            ->method('instantiate')
            ->with($entityClass)
            ->willReturn($entity);

        $this->context->setClassName($entityClass);
        $this->context->setId($entityId);
        $this->context->setConfig($config);
        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame($entity, $this->context->getResult());
    }

    public function testProcessForEntityWithoutIdGeneratorAndEntityAlreadyExists(): void
    {
        $entityClass = Entity\Product::class;
        $entityId = 123;
        $config = new EntityDefinitionConfig();
        $metadata = new EntityMetadata($entityClass);
        $metadata->setHasIdentifierGenerator(false);

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('isManageableEntityClass')
            ->with($entityClass)
            ->willReturn(true);
        $this->entityLoader->expects(self::once())
            ->method('findEntity')
            ->with($entityClass, $entityId, self::identicalTo($metadata))
            ->willReturn(new Entity\Product());
        $this->entityInstantiator->expects(self::never())
            ->method('instantiate');

        $this->context->setClassName($entityClass);
        $this->context->setId($entityId);
        $this->context->setConfig($config);
        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertNull($this->context->getResult());
        self::assertEquals(
            [Error::createConflictValidationError('The entity already exists.')],
            $this->context->getErrors()
        );
    }

    public function testProcessWhenEntityIsAlreadyCreated(): void
    {
        $entityClass = Entity\Product::class;
        $entity = new $entityClass();
        $config = new EntityDefinitionConfig();

        $this->entityInstantiator->expects(self::never())
            ->method('instantiate');

        $this->context->setClassName($entityClass);
        $this->context->setConfig($config);
        $this->context->setResult($entity);
        $this->processor->process($this->context);

        self::assertSame($entity, $this->context->getResult());
    }

    public function testProcessForApiResourceBasedOnManageableEntity(): void
    {
        $entityClass = Entity\UserProfile::class;
        $parentResourceClass = Entity\User::class;
        $entity = new $parentResourceClass();
        $config = new EntityDefinitionConfig();
        $config->setParentResourceClass($parentResourceClass);
        $metadata = new EntityMetadata($entityClass);
        $metadata->setHasIdentifierGenerator(true);

        $this->doctrineHelper->expects(self::exactly(3))
            ->method('isManageableEntityClass')
            ->willReturnMap([
                [$entityClass, false],
                [$parentResourceClass, true]
            ]);
        $this->entityInstantiator->expects(self::once())
            ->method('instantiate')
            ->with($parentResourceClass)
            ->willReturn($entity);

        $this->context->setClassName($entityClass);
        $this->context->setId(123);
        $this->context->setConfig($config);
        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame($entity, $this->context->getResult());
    }

    public function testProcessWhenDataClassIsSpecifiedForForm(): void
    {
        $entityClass = Entity\User::class;
        $formDataClass = Entity\Product::class;
        $entityId = 123;
        $entity = new $formDataClass();
        $config = new EntityDefinitionConfig();
        $config->setFormOption('data_class', $formDataClass);
        $metadata = new EntityMetadata($formDataClass);
        $metadata->setHasIdentifierGenerator(false);

        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with($formDataClass)
            ->willReturn(false);
        $this->entityLoader->expects(self::never())
            ->method('findEntity');
        $this->entityInstantiator->expects(self::once())
            ->method('instantiate')
            ->with($formDataClass)
            ->willReturn($entity);

        $this->context->setClassName($entityClass);
        $this->context->setId($entityId);
        $this->context->setConfig($config);
        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame($entity, $this->context->getResult());
    }

    public function testProcessWhenDataClassIsSpecifiedForFormAndResultIsAlreadySet(): void
    {
        $entityClass = Entity\User::class;
        $formDataClass = Entity\Product::class;
        $entity = new $formDataClass();
        $config = new EntityDefinitionConfig();
        $config->setFormOption('data_class', $formDataClass);

        $this->doctrineHelper->expects(self::never())
            ->method('isManageableEntityClass');
        $this->entityLoader->expects(self::never())
            ->method('findEntity');
        $this->entityInstantiator->expects(self::never())
            ->method('instantiate');

        $this->context->setClassName($entityClass);
        $this->context->setId(123);
        $this->context->setConfig($config);
        $this->context->setResult($entity);
        $this->processor->process($this->context);

        self::assertSame($entity, $this->context->getResult());
    }
}
