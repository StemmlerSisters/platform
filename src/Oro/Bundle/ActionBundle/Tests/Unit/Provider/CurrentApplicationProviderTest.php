<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Provider;

use Oro\Bundle\ActionBundle\Provider\CurrentApplicationProvider;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CurrentApplicationProviderTest extends TestCase
{
    private TokenStorageInterface&MockObject $tokenStorage;
    private CurrentApplicationProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->provider = new CurrentApplicationProvider($this->tokenStorage);
    }

    private function createToken(UserInterface $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        return $token;
    }

    /**
     * @dataProvider isApplicationsValidDataProvider
     */
    public function testIsApplicationsValid(array $applications, ?TokenInterface $token, bool $expectedResult): void
    {
        $this->tokenStorage->expects($this->any())
            ->method('getToken')
            ->willReturn($token);

        $this->assertEquals($expectedResult, $this->provider->isApplicationsValid($applications));
    }

    /**
     * @dataProvider getCurrentApplicationProvider
     */
    public function testGetCurrentApplication(?TokenInterface $token, ?string $expectedResult): void
    {
        $this->tokenStorage->expects($this->any())
            ->method('getToken')
            ->willReturn($token);

        $this->assertSame($expectedResult, $this->provider->getCurrentApplication());
    }

    public function isApplicationsValidDataProvider(): array
    {
        return [
            [
                'applications' => ['default'],
                'token' => $this->createToken(new User()),
                'expectedResult' => true
            ],
            [
                'applications' => ['test'],
                'token' => $this->createToken(new User()),
                'expectedResult' => false
            ],
            [
                'applications' => ['default'],
                'token' => $this->createToken($this->createMock(UserInterface::class)),
                'expectedResult' => false
            ],
            [
                'applications' => ['test'],
                'token' => $this->createToken($this->createMock(UserInterface::class)),
                'expectedResult' => false
            ],
            [
                'applications' => ['default'],
                'token' => null,
                'expectedResult' => false
            ],
            [
                'applications' => [],
                'token' => null,
                'expectedResult' => true
            ],
        ];
    }

    public function getCurrentApplicationProvider(): array
    {
        return [
            'supported user' => [
                'token' => $this->createToken(new User()),
                'expectedResult' => 'default',
            ],
            'not supported user' => [
                'token' => $this->createToken($this->createMock(UserInterface::class)),
                'expectedResult' => null,
            ],
            'empty token' => [
                'token' => null,
                'expectedResult' => null,
            ],
        ];
    }
}
