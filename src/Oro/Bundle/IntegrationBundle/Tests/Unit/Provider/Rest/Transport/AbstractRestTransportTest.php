<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Provider\Rest\Transport;

use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\IntegrationBundle\Exception\InvalidConfigurationException;
use Oro\Bundle\IntegrationBundle\Provider\Rest\Client\RestClientFactoryInterface;
use Oro\Bundle\IntegrationBundle\Provider\Rest\Client\RestClientInterface;
use Oro\Bundle\IntegrationBundle\Provider\Rest\Transport\AbstractRestTransport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

class AbstractRestTransportTest extends TestCase
{
    private RestClientFactoryInterface&MockObject $clientFactory;
    private AbstractRestTransport&MockObject $transport;

    #[\Override]
    protected function setUp(): void
    {
        $this->clientFactory = $this->createMock(RestClientFactoryInterface::class);
        $this->transport = $this->getMockForAbstractClass(AbstractRestTransport::class);
        $this->transport->setRestClientFactory($this->clientFactory);
    }

    public function testInitWorks(): void
    {
        $expectedBaseUrl = 'https://example.com/api/v2';
        $expectedClientOptions = ['auth' => ['username', 'password']];

        $expectedClient = $this->createMock(RestClientInterface::class);

        $entity = $this->createMock(Transport::class);

        $settings = $this->createMock(ParameterBag::class);

        $entity->expects(self::atLeastOnce())
            ->method('getSettingsBag')
            ->willReturn($settings);

        $this->transport->expects(self::once())
            ->method('getClientBaseUrl')
            ->with($settings)
            ->willReturn($expectedBaseUrl);

        $this->transport->expects(self::once())
            ->method('getClientOptions')
            ->with($settings)
            ->willReturn($expectedClientOptions);

        $this->clientFactory->expects(self::once())
            ->method('createRestClient')
            ->with($expectedBaseUrl, $expectedClientOptions)
            ->willReturn($expectedClient);

        $this->transport->init($entity);

        self::assertSame($expectedClient, $this->transport->getClient());
        self::assertSame($expectedClient, $this->transport->getClient());
    }

    public function testGetClientFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("REST Transport isn't configured properly.");

        $this->transport->getClient();
    }
}
