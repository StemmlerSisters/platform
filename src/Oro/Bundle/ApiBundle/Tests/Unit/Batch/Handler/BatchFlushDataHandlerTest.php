<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Batch\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Oro\Bundle\ApiBundle\Batch\Handler\BatchFlushDataHandler;
use Oro\Bundle\ApiBundle\Batch\Handler\BatchUpdateItem;
use Oro\Bundle\ApiBundle\Batch\Processor\UpdateItem\BatchUpdateItemContext;
use Oro\Bundle\ApiBundle\Processor\Context;
use Oro\Bundle\ApiBundle\Processor\Create\CreateContext;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\FlushDataHandlerContext;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\FlushDataHandlerInterface;
use Oro\Bundle\ApiBundle\Processor\Delete\DeleteContext;
use Oro\Bundle\ApiBundle\Processor\Update\UpdateContext;
use Oro\Bundle\ApiBundle\Request\ApiAction;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Component\ChainProcessor\ParameterBagInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BatchFlushDataHandlerTest extends TestCase
{
    private const string ENTITY_CLASS = 'Test\Entity';

    private DoctrineHelper&MockObject $doctrineHelper;
    private FlushDataHandlerInterface&MockObject $flushDataHandler;
    private BatchFlushDataHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->flushDataHandler = $this->createMock(FlushDataHandlerInterface::class);

        $this->handler = new BatchFlushDataHandler(
            self::ENTITY_CLASS,
            $this->doctrineHelper,
            $this->flushDataHandler
        );
    }

    private function prepareBatchUpdateItemContext(
        BatchUpdateItem&MockObject $item,
        string $targetAction,
        bool $hasErrors,
        Context|MockObject|null $itemTargetContext,
        ?object $itemEntity,
        ?bool $isExistingEntity = null
    ): void {
        $itemContext = $this->createMock(BatchUpdateItemContext::class);
        $item->expects(self::once())
            ->method('getContext')
            ->willReturn($itemContext);
        $itemContext->expects(self::any())
            ->method('getTargetAction')
            ->willReturn($targetAction);
        $itemContext->expects(self::any())
            ->method('hasErrors')
            ->willReturn($hasErrors);
        $itemContext->expects(self::any())
            ->method('getTargetContext')
            ->willReturn($itemTargetContext);
        if (null !== $itemTargetContext) {
            $itemTargetContext->expects(self::any())
                ->method('getResult')
                ->willReturn($itemEntity);
            if (null !== $isExistingEntity) {
                $itemTargetContext->expects(self::any())
                    ->method('isExisting')
                    ->willReturn($isExistingEntity);
            } elseif (is_a($itemTargetContext, CreateContext::class)) {
                $itemTargetContext->expects(self::any())
                    ->method('isExisting')
                    ->willReturn(false);
            } elseif (is_a($itemTargetContext, UpdateContext::class)) {
                $itemTargetContext->expects(self::any())
                    ->method('isExisting')
                    ->willReturn(true);
            }
        }
    }

    public function testStartFlushData(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($em);

        $this->handler->startFlushData([$this->createMock(BatchUpdateItem::class)]);
    }

    public function testStartFlushDataWhenHandlerIsAlreadyStarted(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The flush data already started.');

        $em = $this->createMock(EntityManagerInterface::class);

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($em);

        $this->handler->startFlushData([$this->createMock(BatchUpdateItem::class)]);
        $this->handler->startFlushData([$this->createMock(BatchUpdateItem::class)]);
    }

    public function testFinishFlushData(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($em);
        $em->expects(self::never())
            ->method('clear');

        $items = [$this->createMock(BatchUpdateItem::class)];
        $this->handler->startFlushData($items);
        $this->handler->finishFlushData($items);
    }

    public function testFinishFlushDataWhenHandlerIsNotStarted(): void
    {
        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManagerForClass');

        $this->handler->finishFlushData([$this->createMock(BatchUpdateItem::class)]);
    }

    public function testClear(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('clear');

        $items = [$this->createMock(BatchUpdateItem::class)];
        $this->handler->startFlushData($items);
        $this->handler->finishFlushData($items);
        $this->handler->clear();
    }

    public function testClearWhenHandlerIsNotStarted(): void
    {
        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManagerForClass');

        $this->handler->clear();
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testFlushDataWhenNoError(): void
    {
        $sharedData = $this->createMock(ParameterBagInterface::class);

        $item1 = $this->createMock(BatchUpdateItem::class);
        $item1TargetContext = $this->createMock(CreateContext::class);
        $item1Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext($item1, ApiAction::CREATE, false, $item1TargetContext, $item1Entity);
        $item1TargetContext->expects(self::once())
            ->method('getSharedData')
            ->willReturn($sharedData);

        $item2 = $this->createMock(BatchUpdateItem::class);
        $item2TargetContext = $this->createMock(UpdateContext::class);
        $item2Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext($item2, ApiAction::UPDATE, false, $item2TargetContext, $item2Entity);

        $item3 = $this->createMock(BatchUpdateItem::class);
        $item3TargetContext = $this->createMock(DeleteContext::class);
        $item3Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext($item3, ApiAction::DELETE, false, $item3TargetContext, $item3Entity);

        $item4 = $this->createMock(BatchUpdateItem::class);
        $this->prepareBatchUpdateItemContext($item4, ApiAction::CREATE, false, null, null);

        $item5 = $this->createMock(BatchUpdateItem::class);
        $item5TargetContext = $this->createMock(CreateContext::class);
        $this->prepareBatchUpdateItemContext($item5, ApiAction::CREATE, false, $item5TargetContext, null);

        $item6 = $this->createMock(BatchUpdateItem::class);
        $item6TargetContext = $this->createMock(CreateContext::class);
        $item6Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext($item6, ApiAction::CREATE, true, $item6TargetContext, $item6Entity);

        $item7 = $this->createMock(BatchUpdateItem::class);
        $item7TargetContext = $this->createMock(UpdateContext::class);
        $item7Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext(
            $item7,
            ApiAction::UPDATE,
            false,
            $item7TargetContext,
            $item7Entity,
            false
        );

        $item8 = $this->createMock(BatchUpdateItem::class);
        $item8TargetContext = $this->createMock(CreateContext::class);
        $item8Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext(
            $item8,
            ApiAction::CREATE,
            false,
            $item8TargetContext,
            $item8Entity,
            true
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($em);
        $em->expects(self::exactly(2))
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);
        $metadataFactory->expects(self::exactly(2))
            ->method('isTransient')
            ->withConsecutive(
                [get_class($item1Entity)],
                [get_class($item7Entity)]
            )
            ->willReturn(false);
        $em->expects(self::exactly(2))
            ->method('persist')
            ->withConsecutive(
                [self::identicalTo($item1Entity)],
                [self::identicalTo($item7Entity)]
            );

        $entityContexts = [$item1TargetContext, $item2TargetContext, $item7TargetContext, $item8TargetContext];
        $this->flushDataHandler->expects(self::once())
            ->method('flushData')
            ->with(self::identicalTo($em), self::isInstanceOf(FlushDataHandlerContext::class))
            ->willReturnCallback(function (
                EntityManagerInterface $em,
                FlushDataHandlerContext $context
            ) use (
                $entityContexts,
                $sharedData
            ) {
                self::assertCount(count($entityContexts), $context->getEntityContexts());
                foreach ($entityContexts as $i => $entityContext) {
                    self::assertSame(
                        $entityContext,
                        $context->getEntityContexts()[$i],
                        sprintf('Entity Context #%d', $i)
                    );
                }
                self::assertSame($sharedData, $context->getSharedData());
            });

        $items = [$item1, $item2, $item3, $item4, $item5, $item6, $item7, $item8];
        $this->handler->startFlushData($items);
        $this->handler->flushData($items);
    }

    public function testFlushDataWhenNoErrorForNotManageableEntity(): void
    {
        $sharedData = $this->createMock(ParameterBagInterface::class);

        $item1 = $this->createMock(BatchUpdateItem::class);
        $item1TargetContext = $this->createMock(CreateContext::class);
        $item1Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext($item1, ApiAction::CREATE, false, $item1TargetContext, $item1Entity);
        $item1TargetContext->expects(self::once())
            ->method('getSharedData')
            ->willReturn($sharedData);

        $item2 = $this->createMock(BatchUpdateItem::class);
        $item2TargetContext = $this->createMock(UpdateContext::class);
        $item2Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext($item2, ApiAction::UPDATE, false, $item2TargetContext, $item2Entity);

        $item3 = $this->createMock(BatchUpdateItem::class);
        $item3TargetContext = $this->createMock(DeleteContext::class);
        $item3Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext($item3, ApiAction::DELETE, false, $item3TargetContext, $item3Entity);

        $item4 = $this->createMock(BatchUpdateItem::class);
        $this->prepareBatchUpdateItemContext($item4, ApiAction::CREATE, false, null, null);

        $item5 = $this->createMock(BatchUpdateItem::class);
        $item5TargetContext = $this->createMock(CreateContext::class);
        $this->prepareBatchUpdateItemContext($item5, ApiAction::CREATE, false, $item5TargetContext, null);

        $item6 = $this->createMock(BatchUpdateItem::class);
        $item6TargetContext = $this->createMock(CreateContext::class);
        $item6Entity = $this->createMock(\stdClass::class);
        $this->prepareBatchUpdateItemContext($item6, ApiAction::CREATE, true, $item6TargetContext, $item6Entity);

        $em = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);
        $metadataFactory->expects(self::once())
            ->method('isTransient')
            ->with(get_class($item1Entity))
            ->willReturn(true);
        $em->expects(self::never())
            ->method('persist');

        $entityContexts = [$item1TargetContext, $item2TargetContext];
        $this->flushDataHandler->expects(self::once())
            ->method('flushData')
            ->with(self::identicalTo($em), self::isInstanceOf(FlushDataHandlerContext::class))
            ->willReturnCallback(function (
                EntityManagerInterface $em,
                FlushDataHandlerContext $context
            ) use (
                $entityContexts,
                $sharedData
            ) {
                self::assertCount(count($entityContexts), $context->getEntityContexts());
                foreach ($entityContexts as $i => $entityContext) {
                    self::assertSame(
                        $entityContext,
                        $context->getEntityContexts()[$i],
                        sprintf('Entity Context #%d', $i)
                    );
                }
                self::assertSame($sharedData, $context->getSharedData());
            });

        $items = [$item1, $item2, $item3, $item4, $item5, $item6];
        $this->handler->startFlushData($items);
        $this->handler->flushData($items);
    }

    public function testFlushDataWhenFlushFailed(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $exception = new \Exception('some error');

        $this->expectExceptionObject($exception);

        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(self::ENTITY_CLASS)
            ->willReturn($em);
        $this->flushDataHandler->expects(self::once())
            ->method('flushData')
            ->with(self::identicalTo($em))
            ->willThrowException($exception);

        $items = [$this->createMock(BatchUpdateItem::class)];
        $this->handler->startFlushData($items);
        $this->handler->flushData($items);
    }

    public function testFlushDataWhenHandlerNotStarted(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The flush data is not started.');

        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManagerForClass');

        $this->handler->flushData([$this->createMock(BatchUpdateItem::class)]);
    }
}
