<?php

namespace Oro\Bundle\NotificationBundle\Tests\Unit\Form\Handler;

use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\FormBundle\Form\Handler\FormWithAjaxReloadHandler;
use Oro\Bundle\NotificationBundle\Entity\EmailNotification;
use Oro\Bundle\NotificationBundle\Form\Handler\EmailNotificationHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class EmailNotificationHandlerTest extends TestCase
{
    private const FORM_DATA = ['field' => 'value'];

    private FormInterface&MockObject $form;
    private ObjectManager&MockObject $manager;
    private Request $request;
    private EmailNotification $entity;
    private EmailNotificationHandler $handler;
    private FormWithAjaxReloadHandler $formWithAjaxReloadHandler;

    #[\Override]
    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);

        $this->manager = $this->createMock(ObjectManager::class);

        $this->formWithAjaxReloadHandler = $this->createMock(FormWithAjaxReloadHandler::class);

        $this->request = new Request();
        $this->entity = new EmailNotification();

        $this->handler = new EmailNotificationHandler($this->formWithAjaxReloadHandler);
    }

    public function testProcessException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument data should be instance of EmailNotification entity');

        $this->handler->process(new \stdClass(), $this->form, $this->request);
    }

    public function testValidRequest(): void
    {
        $this->formWithAjaxReloadHandler->expects(self::once())
            ->method('process')
            ->with($this->entity, $this->form, $this->request)
            ->willReturn(true);

        $this->assertTrue($this->handler->process($this->entity, $this->form, $this->request));
    }
}
