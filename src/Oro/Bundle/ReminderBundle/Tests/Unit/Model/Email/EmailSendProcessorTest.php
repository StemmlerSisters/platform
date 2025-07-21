<?php

namespace Oro\Bundle\ReminderBundle\Tests\Unit\Model\Email;

use Oro\Bundle\NotificationBundle\Manager\EmailNotificationManager;
use Oro\Bundle\ReminderBundle\Entity\Reminder;
use Oro\Bundle\ReminderBundle\Event\ReminderEvents;
use Oro\Bundle\ReminderBundle\Event\SendReminderEmailEvent;
use Oro\Bundle\ReminderBundle\Model\Email\EmailSendProcessor;
use Oro\Bundle\ReminderBundle\Model\Email\TemplateEmailNotification;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class EmailSendProcessorTest extends TestCase
{
    private EmailNotificationManager&MockObject $emailNotificationManager;
    private TemplateEmailNotification&MockObject $emailNotification;
    private EventDispatcher&MockObject $eventDispatcher;
    private EmailSendProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->emailNotificationManager = $this->createMock(EmailNotificationManager::class);
        $this->emailNotification = $this->createMock(TemplateEmailNotification::class);
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);

        $this->processor = new EmailSendProcessor(
            $this->emailNotificationManager,
            $this->emailNotification,
            $this->eventDispatcher
        );
    }

    public function testPush(): void
    {
        $fooReminder = $this->createMock(Reminder::class);
        $barReminder = $this->createMock(Reminder::class);

        $this->processor->push($fooReminder);
        $this->processor->push($barReminder);

        self::assertEquals(
            [$fooReminder, $barReminder],
            ReflectionUtil::getPropertyValue($this->processor, 'reminders')
        );
    }

    public function testProcess(): void
    {
        $fooReminder = $this->createMock(Reminder::class);
        $barReminder = $this->createMock(Reminder::class);

        $this->emailNotification->expects($this->exactly(2))
            ->method('setReminder')
            ->withConsecutive([$fooReminder], [$barReminder]);

        $this->emailNotificationManager->expects($this->exactly(2))
            ->method('processSingle')
            ->with($this->emailNotification);

        $fooReminder->expects($this->once())
            ->method('setState')
            ->with(Reminder::STATE_SENT);

        $barReminder->expects($this->once())
            ->method('setState')
            ->with(Reminder::STATE_SENT);

        $barEvent = new SendReminderEmailEvent($barReminder);
        $fooEvent = new SendReminderEmailEvent($fooReminder);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$barEvent, ReminderEvents::BEFORE_REMINDER_EMAIL_NOTIFICATION_SEND],
                [$fooEvent, ReminderEvents::BEFORE_REMINDER_EMAIL_NOTIFICATION_SEND]
            );

        $this->processor->push($fooReminder);
        $this->processor->push($barReminder);

        $this->processor->process();
    }

    public function testProcessFailed(): void
    {
        $fooReminder = $this->createMock(Reminder::class);

        $this->emailNotification->expects($this->once())
            ->method('setReminder')
            ->with($fooReminder);

        $exception = new \Exception();

        $this->emailNotificationManager->expects($this->once())
            ->method('processSingle')
            ->with($this->emailNotification)
            ->willThrowException($exception);

        $fooReminder->expects($this->once())
            ->method('setState')
            ->with(Reminder::STATE_FAIL);

        $fooReminder->expects($this->once())
            ->method('setFailureException')
            ->with($exception);

        $this->processor->push($fooReminder);

        $this->processor->process();
    }

    public function testGetLabel(): void
    {
        $this->assertEquals(
            'oro.reminder.processor.email.label',
            $this->processor->getLabel()
        );
    }
}
