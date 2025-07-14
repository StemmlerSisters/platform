<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Session;

use Oro\Bundle\SecurityBundle\Session\SessionHandlerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class SessionHandlerFactoryTest extends TestCase
{
    private ServiceLocator $locatorMock;

    #[\Override]
    protected function setUp(): void
    {
        $this->locatorMock = $this->createMock(ServiceLocator::class);
    }

    /**
     * @dataProvider validDsnDataProvider
     */
    public function testSessionHandlerInstanceReturned(string $dns, $expectedAlias): void
    {
        $sessionHandlerMock = $this->createMock(\SessionHandlerInterface::class);
        $this->locatorMock->expects(self::once())
            ->method('get')
            ->with($expectedAlias)
            ->willReturn($sessionHandlerMock);

        self::assertEquals(
            $sessionHandlerMock,
            SessionHandlerFactory::create($this->locatorMock, $dns)
        );
    }

    public function validDsnDataProvider(): array
    {
        return [
            ['redis:///foo/bar.sock/4', 'redis'],
            ['redis://password@127.0.0.1:6379/0', 'redis'],
            ['native:', 'native'],
            ['pdo:', 'pdo']
        ];
    }

    public function testEmptySchemeException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "//localhost" session handler DSN must contain a scheme.');
        SessionHandlerFactory::create($this->locatorMock, '//localhost');
    }
}
