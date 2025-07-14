<?php

namespace Oro\Bundle\MessageQueueBundle\Tests\Unit\Profiler;

use Oro\Bundle\MessageQueueBundle\Profiler\MessageQueueCollector;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Client\TraceableMessageProducer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageQueueCollectorTest extends TestCase
{
    public function testCouldBeConstructedWithMessageProducerAsFirstArgument(): void
    {
        new MessageQueueCollector($this->createMock(MessageProducerInterface::class));
    }

    public function testShouldReturnExpectedName(): void
    {
        $collector = new MessageQueueCollector($this->createMock(MessageProducerInterface::class));

        $this->assertEquals('message_queue', $collector->getName());
    }

    public function testShouldReturnEmptySentMessageArrayIfNotTraceableMessageProducer(): void
    {
        $collector = new MessageQueueCollector($this->createMock(MessageProducerInterface::class));

        $collector->collect(new Request(), new Response());

        $this->assertSame([], $collector->getSentMessages());
    }

    public function testShouldReturnSentMessageArrayTakenFromTraceableMessageProducer(): void
    {
        $producer = $this->createMock(TraceableMessageProducer::class);
        $producer->expects($this->once())
            ->method('getTraces')
            ->willReturn([['foo'], ['bar']]);

        $collector = new MessageQueueCollector($producer);

        $collector->collect(new Request(), new Response());

        $this->assertSame([['foo'], ['bar']], $collector->getSentMessages());
    }

    public function testShouldPrettyPrintKnownPriority(): void
    {
        $collector = new MessageQueueCollector($this->createMock(MessageProducerInterface::class));

        $this->assertEquals('normal', $collector->prettyPrintPriority(MessagePriority::NORMAL));
    }

    public function testShouldPrettyPrintUnknownPriority(): void
    {
        $collector = new MessageQueueCollector($this->createMock(MessageProducerInterface::class));

        $this->assertEquals('unknownPriority', $collector->prettyPrintPriority('unknownPriority'));
    }

    public function testShouldPrettyPrintScalarMessage(): void
    {
        $collector = new MessageQueueCollector($this->createMock(MessageProducerInterface::class));

        $this->assertEquals('foo', $collector->prettyPrintMessage('foo'));
        $this->assertEquals('&lt;p&gt;', $collector->prettyPrintMessage('<p>'));
    }

    public function testShouldPrettyPrintArrayMessage(): void
    {
        $collector = new MessageQueueCollector($this->createMock(MessageProducerInterface::class));

        $expected = "[\n    &quot;foo&quot;,\n    &quot;bar&quot;\n]";

        $this->assertEquals($expected, $collector->prettyPrintMessage(['foo', 'bar']));
    }
}
