<?php

namespace Oro\Bundle\TranslationBundle\Tests\Unit\Async;

use Oro\Bundle\MessageQueueBundle\Client\MessageBuffer;
use Oro\Bundle\TranslationBundle\Async\DumpJsTranslationsMessageFilter;
use Oro\Bundle\TranslationBundle\Async\Topic\DumpJsTranslationsTopic;
use PHPUnit\Framework\TestCase;

class DumpJsTranslationsMessageFilterTest extends TestCase
{
    private DumpJsTranslationsMessageFilter $filter;

    #[\Override]
    protected function setUp(): void
    {
        $this->filter = new DumpJsTranslationsMessageFilter();
    }

    public function testApplyForEmptyBuffer(): void
    {
        $buffer = new MessageBuffer();
        $this->filter->apply($buffer);
        self::assertEquals([], $buffer->getMessages());
    }

    public function testApplyWhenNoDuplicates(): void
    {
        $buffer = new MessageBuffer();

        $buffer->addMessage(DumpJsTranslationsTopic::getName(), []);
        $buffer->addMessage('another', []);

        $this->filter->apply($buffer);

        self::assertEquals(
            [
                0 => [DumpJsTranslationsTopic::getName(), []],
                1 => ['another', []]
            ],
            $buffer->getMessages()
        );
    }

    public function testApplyWhenDuplicatesExist(): void
    {
        $buffer = new MessageBuffer();

        $buffer->addMessage(DumpJsTranslationsTopic::getName(), []);
        $buffer->addMessage(DumpJsTranslationsTopic::getName(), []);
        $buffer->addMessage('another', []);
        $buffer->addMessage(DumpJsTranslationsTopic::getName(), []);

        $this->filter->apply($buffer);

        self::assertEquals(
            [
                0 => [DumpJsTranslationsTopic::getName(), []],
                2 => ['another', []]
            ],
            $buffer->getMessages()
        );
    }
}
