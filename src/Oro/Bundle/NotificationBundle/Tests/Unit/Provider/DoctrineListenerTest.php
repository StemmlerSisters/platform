<?php

namespace Oro\Bundle\NotificationBundle\Tests\Unit\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Oro\Bundle\NotificationBundle\Doctrine\EntityPool;
use Oro\Bundle\NotificationBundle\Provider\DoctrineListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

class DoctrineListenerTest extends TestCase
{
    private EntityPool&MockObject $entityPool;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private DoctrineListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityPool = $this->createMock(EntityPool::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->listener = new DoctrineListener($this->entityPool, $this->eventDispatcher);
    }

    public function testPostFlush(): void
    {
        $args = $this->createMock(PostFlushEventArgs::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $args->expects($this->once())
            ->method('getObjectManager')
            ->willReturn($entityManager);

        $this->entityPool->expects($this->once())
            ->method('persistAndFlush')
            ->with($entityManager);

        $this->listener->postFlush($args);
    }

    /**
     * @dataProvider eventDataProvider
     */
    public function testEventDispatchers(string $methodName, string $eventName): void
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn('something');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(Event::class), $eventName);

        $this->listener->$methodName($args);
    }

    public function eventDataProvider(): array
    {
        return [
            'post update event case'  => [
                'method name'            => 'postUpdate',
                'expected event name'    => 'oro.notification.event.entity_post_update'
            ],
            'post persist event case' => [
                'method name'            => 'postPersist',
                'expected event name'    => 'oro.notification.event.entity_post_persist'
            ],
            'post remove event case'  => [
                'method name'            => 'postRemove',
                'expected event name'    => 'oro.notification.event.entity_post_remove'
            ],
        ];
    }
}
