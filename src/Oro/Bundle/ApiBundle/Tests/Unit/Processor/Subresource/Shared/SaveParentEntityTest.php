<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\Shared;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\FlushDataHandlerContext;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\FlushDataHandlerInterface;
use Oro\Bundle\ApiBundle\Processor\Subresource\Shared\SaveParentEntity;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\ChangeRelationshipProcessorTestCase;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use PHPUnit\Framework\MockObject\MockObject;

class SaveParentEntityTest extends ChangeRelationshipProcessorTestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;
    private FlushDataHandlerInterface&MockObject $flushDataHandler;
    private SaveParentEntity $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->flushDataHandler = $this->createMock(FlushDataHandlerInterface::class);

        $this->processor = new SaveParentEntity($this->doctrineHelper, $this->flushDataHandler);
    }

    public function testProcessWhenParentEntityAlreadySaved(): void
    {
        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->flushDataHandler->expects(self::never())
            ->method('flushData');

        $this->context->setProcessed(SaveParentEntity::OPERATION_NAME);
        $this->context->setParentEntity(new \stdClass());
        $this->processor->process($this->context);
    }

    public function testProcessWhenNoParentEntity(): void
    {
        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->flushDataHandler->expects(self::never())
            ->method('flushData');

        $this->processor->process($this->context);
        self::assertFalse($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }

    public function testProcessForNotSupportedParentEntity(): void
    {
        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->flushDataHandler->expects(self::never())
            ->method('flushData');

        $this->context->setParentEntity(null);
        $this->processor->process($this->context);
        self::assertFalse($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }

    public function testProcessForNotManageableParentEntity(): void
    {
        $entity = new \stdClass();

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManager')
            ->with(self::identicalTo($entity), false)
            ->willReturn(null);

        $this->flushDataHandler->expects(self::never())
            ->method('flushData');

        $this->context->setParentEntity($entity);
        $this->processor->process($this->context);
        self::assertFalse($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }

    public function testProcessForManageableParentEntity(): void
    {
        $entity = new \stdClass();

        $em = $this->createMock(EntityManagerInterface::class);

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManager')
            ->with(self::identicalTo($entity), false)
            ->willReturn($em);

        $this->flushDataHandler->expects(self::once())
            ->method('flushData')
            ->with(self::identicalTo($em), self::isInstanceOf(FlushDataHandlerContext::class))
            ->willReturnCallback(function (EntityManagerInterface $em, FlushDataHandlerContext $context) {
                self::assertSame($this->context, $context->getEntityContexts()[0]);
                self::assertSame($this->context->getSharedData(), $context->getSharedData());
            });

        $this->context->setParentEntity($entity);
        $this->processor->process($this->context);
        self::assertTrue($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }
}
