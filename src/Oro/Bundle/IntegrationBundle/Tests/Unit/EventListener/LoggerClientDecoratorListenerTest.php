<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\EventListener;

use Oro\Bundle\IntegrationBundle\Event\ClientCreatedAfterEvent;
use Oro\Bundle\IntegrationBundle\EventListener\LoggerClientDecoratorListener;
use Oro\Bundle\IntegrationBundle\Provider\Rest\Client\Decorator\LoggerClientDecorator;
use Oro\Bundle\IntegrationBundle\Provider\Rest\Client\RestClientInterface;
use Oro\Bundle\IntegrationBundle\Provider\Rest\Transport\RestTransportSettingsInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoggerClientDecoratorListenerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private LoggerClientDecoratorListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->listener = new LoggerClientDecoratorListener();
        $this->listener->setLogger($this->logger);
    }

    public function testDecoratorAttached(): void
    {
        $client = $this->createMock(RestClientInterface::class);
        $transport = $this->createMock(RestTransportSettingsInterface::class);
        $transport->expects($this->any())
            ->method('getOptions')
            ->willReturn([]);

        $event = new ClientCreatedAfterEvent($client, $transport);
        $this->listener->onClientCreated($event);

        $this->assertInstanceOf(
            LoggerClientDecorator::class,
            $event->getClient(),
            'decorator must be attached to client'
        );
    }

    public function testDecoratorNotAttached(): void
    {
        $configuration = [
            LoggerClientDecoratorListener::CONFIG_KEY => [
                'enabled' => false
            ]
        ];

        $client = $this->createMock(RestClientInterface::class);
        $transport = $this->createMock(RestTransportSettingsInterface::class);
        $transport->expects($this->any())
            ->method('getOptions')
            ->willReturn($configuration);

        $event = new ClientCreatedAfterEvent($client, $transport);
        $this->listener->onClientCreated($event);

        $this->assertSame($client, $event->getClient());
    }
}
