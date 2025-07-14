<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\EventListener;

use Oro\Bundle\UserBundle\EventListener\PasswordChangeExceptionListener;
use Oro\Bundle\UserBundle\Exception\PasswordChangedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordChangeExceptionListenerTest extends TestCase
{
    private SessionInterface&MockObject $session;
    private RequestStack&MockObject $requestStack;
    private TranslatorInterface&MockObject $translator;
    private PasswordChangeExceptionListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->session = $this->createMock(Session::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->expects($this->any())
            ->method('getSession')
            ->willReturn($this->session);
        $this->listener = new PasswordChangeExceptionListener(
            $this->requestStack,
            $this->translator
        );
    }

    public function testOnKernelExceptionNotPasswordChanged(): void
    {
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new \Exception()
        );

        $this->session->expects($this->never())
            ->method($this->anything());

        $this->listener->onKernelException($event);
    }

    public function testOnKernelExceptionPasswordChanged(): void
    {
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new PasswordChangedException()
        );

        $this->translator->expects($this->once())
            ->method('trans')
            ->willReturnArgument(0);

        $flashBag = $this->createMock(FlashBagInterface::class);
        $this->session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $flashBag->expects($this->once())
            ->method('add')
            ->with('error', 'oro.user.security.password_changed.message');

        $this->listener->onKernelException($event);
    }
}
