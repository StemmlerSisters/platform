<?php

namespace Oro\Bundle\SyncBundle\Tests\Unit\Client;

use Oro\Bundle\DistributionBundle\Handler\ApplicationState;
use Oro\Bundle\SyncBundle\Client\ConnectionChecker;
use Oro\Bundle\SyncBundle\Client\WebsocketClientInterface;
use Oro\Bundle\SyncBundle\Provider\WebsocketClientParametersProviderInterface;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConnectionCheckerTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private WebsocketClientInterface&MockObject $client;
    private ApplicationState&MockObject $applicationState;
    private WebsocketClientParametersProviderInterface&MockObject $websocketClientParametersProvider;
    private ConnectionChecker $checker;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = $this->createMock(WebsocketClientInterface::class);
        $this->applicationState = $this->createMock(ApplicationState::class);
        $this->websocketClientParametersProvider = $this->createMock(WebsocketClientParametersProviderInterface::class);

        $this->checker = new ConnectionChecker(
            $this->client,
            $this->applicationState,
            $this->websocketClientParametersProvider
        );
        $this->setUpLoggerMock($this->checker);
    }

    public function testCheckConnection(): void
    {
        $this->websocketClientParametersProvider->expects(self::exactly(2))
            ->method('getHost')
            ->willReturn('example.org');
        $this->client->expects(self::once())
            ->method('connect');
        $this->client->expects(self::once())
            ->method('isConnected')
            ->willReturn(true);

        self::assertTrue($this->checker->checkConnection());

        // Checks that connection check result is cached
        self::assertTrue($this->checker->checkConnection());
    }

    public function testCheckConnectionWhenNotConfigured(): void
    {
        $this->websocketClientParametersProvider->expects(self::once())
            ->method('getHost')
            ->willReturn('');
        $this->client->expects(self::never())
            ->method('connect');
        $this->client->expects(self::never())
            ->method('isConnected');

        self::assertFalse($this->checker->checkConnection());
    }

    public function testWsConnectedFail(): void
    {
        $this->websocketClientParametersProvider->expects(self::exactly(2))
            ->method('getHost')
            ->willReturn('example.org');
        $this->client->expects(self::once())
            ->method('connect');
        $this->client->expects(self::once())
            ->method('isConnected')
            ->willReturn(false);

        self::assertFalse($this->checker->checkConnection());

        // Checks that connection check result is cached
        self::assertFalse($this->checker->checkConnection());
    }

    public function testReset(): void
    {
        $this->websocketClientParametersProvider->expects(self::exactly(3))
            ->method('getHost')
            ->willReturn('example.org');
        $this->client->expects(self::exactly(2))
            ->method('connect');
        $this->client->expects(self::exactly(2))
            ->method('isConnected')
            ->willReturn(false);

        self::assertFalse($this->checker->checkConnection());

        // Checks that connection check result is cached
        self::assertFalse($this->checker->checkConnection());

        $this->checker->reset();

        self::assertFalse($this->checker->checkConnection());
    }

    public function testWsConnectedExceptionDuringInstallNoApplicationState(): void
    {
        $this->websocketClientParametersProvider->expects(self::exactly(2))
            ->method('getHost')
            ->willReturn('example.org');
        $exception = new \Exception('sample message');
        $this->client->expects(self::once())
            ->method('connect')
            ->willThrowException($exception);
        $this->client->expects(self::never())
            ->method('isConnected');
        $this->loggerMock->expects(self::never())
            ->method(self::anything());

        self::assertFalse($this->checker->checkConnection());

        // Checks that connection check result is cached
        self::assertFalse($this->checker->checkConnection());
    }

    public function testWsConnectedExceptionDuringInstall(): void
    {
        $this->websocketClientParametersProvider->expects(self::exactly(2))
            ->method('getHost')
            ->willReturn('example.org');
        $exception = new \Exception('sample message');
        $this->client->expects(self::once())
            ->method('connect')
            ->willThrowException($exception);
        $this->client->expects(self::never())
            ->method('isConnected');
        $this->loggerMock->expects(self::never())
            ->method(self::anything());

        $this->applicationState->expects(self::once())
            ->method('isInstalled')
            ->willReturn(false);

        self::assertFalse($this->checker->checkConnection());

        // Checks that connection check result is cached
        self::assertFalse($this->checker->checkConnection());
    }

    public function testWsConnectedException(): void
    {
        $this->websocketClientParametersProvider->expects(self::exactly(2))
            ->method('getHost')
            ->willReturn('example.org');
        $exception = new \Exception('sample message');
        $this->client->expects(self::once())
            ->method('connect')
            ->willThrowException($exception);
        $this->client->expects(self::never())
            ->method('isConnected');
        $this->loggerMock->expects(self::once())
            ->method('error')
            ->with(
                'Failed to connect to websocket server: {message}',
                ['message' => $exception->getMessage(), 'e' => $exception]
            );

        $this->applicationState->expects(self::once())
            ->method('isInstalled')
            ->willReturn(true);

        self::assertFalse($this->checker->checkConnection());

        // Checks that connection check result is cached
        self::assertFalse($this->checker->checkConnection());
    }

    public function testIsConfiguredWhenHasHost(): void
    {
        $this->websocketClientParametersProvider->expects(self::once())
            ->method('getHost')
            ->willReturn('example.org');

        self::assertTrue($this->checker->isConfigured());
    }

    public function testIsConfiguredWhenNoHost(): void
    {
        $this->websocketClientParametersProvider->expects(self::once())
            ->method('getHost')
            ->willReturn('');

        self::assertFalse($this->checker->isConfigured());
    }
}
