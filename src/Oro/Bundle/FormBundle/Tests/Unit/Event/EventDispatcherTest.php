<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Event;

use Oro\Bundle\FormBundle\Event\EventDispatcher;
use Oro\Bundle\FormBundle\Event\FormHandler\FormProcessEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\Event;

class EventDispatcherTest extends TestCase
{
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private EventDispatcher $immutableDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->immutableDispatcher = new EventDispatcher($this->eventDispatcher);
    }

    public function testDispatchNonFormEvent(): void
    {
        $event = new Event();
        $eventName = 'test_event_name';
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event, $eventName);

        $this->immutableDispatcher->dispatch($event, $eventName);
    }

    public function testDispatchFormEvent(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('getName')
            ->willReturn('form_name');

        $event = new FormProcessEvent($form, []);
        $eventName = 'test_event_name';
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$event, $eventName],
                [$event, $eventName . '.form_name']
            );

        $this->immutableDispatcher->dispatch($event, $eventName);
    }
}
