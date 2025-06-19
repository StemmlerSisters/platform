<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Batch\Processor\Update;

use Oro\Bundle\ApiBundle\Batch\Handler\BatchUpdateItem;
use Oro\Bundle\ApiBundle\Batch\Processor\Update\NormalizeErrors;
use Oro\Bundle\ApiBundle\Batch\Processor\UpdateItem\BatchUpdateItemContext;
use Oro\Bundle\ApiBundle\Model\Error;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\Translation\TranslatorInterface;

class NormalizeErrorsTest extends BatchUpdateProcessorTestCase
{
    private TranslatorInterface&MockObject $translator;
    private NormalizeErrors $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->processor = new NormalizeErrors($this->translator);
    }

    public function testProcessWithoutErrors(): void
    {
        $this->processor->process($this->context);
    }

    public function testProcessWithErrors(): void
    {
        $error = $this->createMock(Error::class);

        $error->expects(self::once())
            ->method('trans')
            ->with(self::identicalTo($this->translator));

        $this->context->addError($error);
        $this->processor->process($this->context);
    }

    public function testProcessWithErrorsInBatchItem(): void
    {
        $error = $this->createMock(Error::class);

        $item = $this->createMock(BatchUpdateItem::class);
        $itemContext = $this->createMock(BatchUpdateItemContext::class);
        $item->expects(self::once())
            ->method('getContext')
            ->willReturn($itemContext);
        $itemContext->expects(self::once())
            ->method('getErrors')
            ->willReturn([$error]);

        $error->expects(self::once())
            ->method('trans')
            ->with(self::identicalTo($this->translator));

        $this->context->setBatchItems([$item]);
        $this->processor->process($this->context);
    }
}
