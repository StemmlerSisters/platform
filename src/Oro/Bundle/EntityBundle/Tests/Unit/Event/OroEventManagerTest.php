<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Event;

use Oro\Bundle\EntityBundle\Event\OroEventManager;
use Oro\Bundle\EntityBundle\Tests\Unit\Event\Stub\StubEventListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class OroEventManagerTest extends TestCase
{
    private ContainerInterface&MockObject $container;
    private OroEventManager $manager;

    #[\Override]
    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);

        $this->manager = new OroEventManager($this->container);
    }

    public function testDisableAndReset(): void
    {
        $this->assertFalse($this->manager->hasDisabledListeners());
        $this->manager->disableListeners();
        $this->assertTrue($this->manager->hasDisabledListeners());
        $this->manager->clearDisabledListeners();
        $this->assertFalse($this->manager->hasDisabledListeners());
    }

    /**
     * @dataProvider dispatchEventDataProvider
     */
    public function testDispatchEvent(bool $isEnabled): void
    {
        $eventName = 'postFlush';

        $affectedListener = new StubEventListener();
        $this->assertFalse($affectedListener->isFlushed);

        $notAffectedListener = $this->createMock(StubEventListener::class);
        $notAffectedListener->expects($this->once())
            ->method($eventName);

        $listenerService = 'test.listener.service';
        $this->container->expects($this->once())
            ->method('get')
            ->with($listenerService)
            ->willReturn($affectedListener);

        $this->manager->addEventListener([$eventName], $listenerService);     // class name Oro\Bundle\*
        $this->manager->addEventListener([$eventName], $notAffectedListener); // class name Mock_*

        if (!$isEnabled) {
            $this->manager->disableListeners('^Oro');
        }
        $this->manager->dispatchEvent($eventName);

        $this->assertEquals($isEnabled, $affectedListener->isFlushed);
    }

    public function dispatchEventDataProvider(): array
    {
        return [
            'enabled'  => [true],
            'disabled' => [false],
        ];
    }
}
