<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Cache;

use Oro\Bundle\WorkflowBundle\Cache\EventTriggerCache;
use Oro\Bundle\WorkflowBundle\Cache\EventTriggerCacheWarmer;
use PHPUnit\Framework\TestCase;

class EventTriggerCacheWarmerTest extends TestCase
{
    private EventTriggerCacheWarmer $warmer;

    #[\Override]
    protected function setUp(): void
    {
        $this->warmer = new EventTriggerCacheWarmer();
    }

    public function testWarmUp(): void
    {
        $eventTriggerCache1 = $this->createMock(EventTriggerCache::class);
        $eventTriggerCache1->expects($this->once())
            ->method('build');

        $eventTriggerCache2 = $this->createMock(EventTriggerCache::class);
        $eventTriggerCache2->expects($this->once())
            ->method('build');

        $this->warmer->addEventTriggerCache($eventTriggerCache1);
        $this->warmer->addEventTriggerCache($eventTriggerCache2);
        $this->warmer->warmUp('test');
    }

    public function testIsOptional(): void
    {
        $this->assertTrue($this->warmer->isOptional());
    }
}
