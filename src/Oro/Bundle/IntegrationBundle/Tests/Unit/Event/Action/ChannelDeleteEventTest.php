<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Event\Action;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Event\Action\ChannelDeleteEvent;
use PHPUnit\Framework\TestCase;

class ChannelDeleteEventTest extends TestCase
{
    public function testGetName(): void
    {
        $event = new ChannelDeleteEvent(new Channel());

        self::assertSame('oro_integration.channel_delete', $event->getName());
    }

    public function testSettersGetters(): void
    {
        $channel = new Channel();
        $event = new ChannelDeleteEvent($channel);

        $event->addError('error1');

        self::assertSame($channel, $event->getChannel());
        self::assertEquals(new ArrayCollection(['error1']), $event->getErrors());
    }
}
