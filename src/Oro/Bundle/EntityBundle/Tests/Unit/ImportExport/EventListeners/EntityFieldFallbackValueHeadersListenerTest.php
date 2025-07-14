<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ImportExport\EventListeners;

use Oro\Bundle\EntityBundle\Entity\EntityFieldFallbackValue;
use Oro\Bundle\EntityBundle\ImportExport\EventListeners\EntityFieldFallbackValueHeadersListener;
use Oro\Bundle\ImportExportBundle\Event\LoadEntityRulesAndBackendHeadersEvent;
use PHPUnit\Framework\TestCase;

class EntityFieldFallbackValueHeadersListenerTest extends TestCase
{
    private EntityFieldFallbackValueHeadersListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->listener = new EntityFieldFallbackValueHeadersListener();
    }

    public function testAfterLoadEntityRulesAndBackendHeaders(): void
    {
        $event = new LoadEntityRulesAndBackendHeadersEvent(EntityFieldFallbackValue::class, [], [], ':', 'full', true);
        $this->listener->afterLoadEntityRulesAndBackendHeaders($event);
        $this->assertSame([['value' => 'value', 'order' => 10005]], $event->getHeaders());
        $this->assertSame(['value' => ['value' => 'value', 'order' => 10005]], $event->getRules());
    }

    public function testAfterLoadEntityRulesAndBackendHeadersDuplicateHeader(): void
    {
        $event = new LoadEntityRulesAndBackendHeadersEvent(
            EntityFieldFallbackValue::class,
            [['value' => 'headerTitle'], ['value' => 'value']],
            [['someRule' => ['value' => 'headerTitle']], ['value' => ['value' => 'value']]],
            ':',
            'full',
            true
        );
        $this->listener->afterLoadEntityRulesAndBackendHeaders($event);
        $this->assertSame([
            ['value' => 'headerTitle'],
            ['value' => 'value']
        ], $event->getHeaders());
        $this->assertSame(
            [
                ['someRule' => ['value' => 'headerTitle']],
                ['value' => ['value' => 'value']]
            ],
            $event->getRules()
        );
    }
}
