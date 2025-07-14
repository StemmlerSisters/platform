<?php

namespace Oro\Bundle\MessageQueueBundle\Tests\Unit\Security;

use Oro\Bundle\MessageQueueBundle\Security\SecurityTokenProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SecurityTokenProviderTest extends TestCase
{
    private TokenStorageInterface&MockObject $tokenStorage;
    private SecurityTokenProvider $tokenProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->tokenProvider = new SecurityTokenProvider($this->tokenStorage);
    }

    public function testGetTokenWhenTokenDoesNotExist(): void
    {
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn(null);

        self::assertNull($this->tokenProvider->getToken());
    }

    public function testGetTokenWhenTokenExists(): void
    {
        $token = $this->createMock(TokenInterface::class);

        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($token);

        self::assertSame($token, $this->tokenProvider->getToken());
    }
}
