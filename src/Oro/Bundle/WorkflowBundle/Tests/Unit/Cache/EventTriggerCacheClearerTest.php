<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Cache;

use Oro\Bundle\WorkflowBundle\Cache\EventTriggerCache;
use Oro\Bundle\WorkflowBundle\Cache\EventTriggerCacheClearer;
use PHPUnit\Framework\TestCase;

class EventTriggerCacheClearerTest extends TestCase
{
    private EventTriggerCacheClearer $clearer;

    #[\Override]
    protected function setUp(): void
    {
        $this->clearer = new EventTriggerCacheClearer();
    }

    public function testClear(): void
    {
        $eventTriggerCache1 = $this->createMock(EventTriggerCache::class);
        $eventTriggerCache1->expects($this->once())
            ->method('invalidateCache');

        $eventTriggerCache2 = $this->createMock(EventTriggerCache::class);
        $eventTriggerCache2->expects($this->once())
            ->method('invalidateCache');

        $this->clearer->addEventTriggerCache($eventTriggerCache1);
        $this->clearer->addEventTriggerCache($eventTriggerCache2);
        $this->clearer->clear('test');
    }
}
