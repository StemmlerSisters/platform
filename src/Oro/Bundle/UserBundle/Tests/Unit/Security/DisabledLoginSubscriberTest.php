<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\Security;

use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestEnumValue;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Oro\Bundle\UserBundle\Security\DisabledLoginSubscriber;
use Oro\Bundle\UserBundle\Tests\Unit\Stub\UserStub as User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class DisabledLoginSubscriberTest extends TestCase
{
    private TokenInterface&MockObject $token;
    private TokenStorageInterface&MockObject $tokenStorage;
    private User $user;

    #[\Override]
    protected function setUp(): void
    {
        $this->user = new User();
        $this->token = $this->createMock(TokenInterface::class);
        $this->token->expects(self::any())
            ->method('getUser')
            ->willReturn($this->user);

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
    }

    public function testOnKernelRequestWithExpiredUser(): void
    {
        $enum = new TestEnumValue('test_enum_code', 'Test', UserManager::STATUS_RESET);
        $this->user->setAuthStatus($enum);

        $this->tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(null);

        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($this->token);

        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('set');

        $request = $this->createMock(Request::class);
        $request->expects(self::once())
            ->method('hasSession')
            ->willReturn(true);
        $request->expects(self::any())
            ->method('getSession')
            ->willReturn($session);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->expects(self::any())
            ->method('getRequest')
            ->willReturn($request);

        $disabledLoginSubscriber = new DisabledLoginSubscriber($this->tokenStorage);
        $disabledLoginSubscriber->onKernelRequest($requestEvent);
    }

    public function testOnKernelRequestWithAllowedUser(): void
    {
        // custom added status
        $enum = new TestEnumValue('test_enum_code', 'test', 'allowed');
        $this->user->setAuthStatus($enum);
        $this->tokenStorage->expects(self::never())
            ->method('setToken')
            ->with(null);

        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($this->token);

        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::never())
            ->method('set');

        $request = $this->createMock(Request::class);
        $request->expects(self::never())
            ->method('hasSession')
            ->willReturn(true);
        $request->expects(self::any())
            ->method('getSession')
            ->willReturn($session);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->expects(self::any())
            ->method('getRequest')
            ->willReturn($request);

        $disabledLoginSubscriber = new DisabledLoginSubscriber($this->tokenStorage);
        $disabledLoginSubscriber->onKernelRequest($requestEvent);
    }
}
