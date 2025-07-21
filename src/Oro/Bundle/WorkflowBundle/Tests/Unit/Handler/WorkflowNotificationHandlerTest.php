<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Handler;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityExtendBundle\PropertyAccess;
use Oro\Bundle\NotificationBundle\Entity\EmailNotification;
use Oro\Bundle\NotificationBundle\Event\Handler\TemplateEmailNotificationAdapter;
use Oro\Bundle\NotificationBundle\Event\NotificationEvent;
use Oro\Bundle\NotificationBundle\Manager\EmailNotificationManager;
use Oro\Bundle\NotificationBundle\Provider\ChainAdditionalEmailAssociationProvider;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowTransitionRecord;
use Oro\Bundle\WorkflowBundle\Event\WorkflowNotificationEvent;
use Oro\Bundle\WorkflowBundle\Handler\WorkflowNotificationHandler;
use Oro\Bundle\WorkflowBundle\Tests\Unit\Stub\EmailNotificationStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class WorkflowNotificationHandlerTest extends TestCase
{
    private const WORKFLOW_NAME = 'test_workflow_name';
    private const TRANSITION_NAME = 'transition_name';

    private ManagerRegistry&MockObject $doctrine;
    private EmailNotificationManager&MockObject $manager;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ChainAdditionalEmailAssociationProvider&MockObject $additionalEmailAssociationProvider;
    private \stdClass $entity;
    private WorkflowNotificationEvent&MockObject $event;
    private WorkflowNotificationHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->manager = $this->createMock(EmailNotificationManager::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->additionalEmailAssociationProvider = $this->createMock(ChainAdditionalEmailAssociationProvider::class);

        $this->entity = new \stdClass();
        $this->event = $this->createMock(WorkflowNotificationEvent::class);
        $this->event->expects($this->any())
            ->method('getEntity')
            ->willReturn($this->entity);

        $this->handler = new WorkflowNotificationHandler(
            $this->manager,
            $this->doctrine,
            PropertyAccess::createPropertyAccessor(),
            $this->eventDispatcher,
            $this->additionalEmailAssociationProvider
        );
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(array $notifications, array $expected): void
    {
        $expected = array_map(
            function (EmailNotification $notification) {
                return new TemplateEmailNotificationAdapter(
                    $this->entity,
                    $notification,
                    $this->doctrine,
                    PropertyAccess::createPropertyAccessor(),
                    $this->eventDispatcher,
                    $this->additionalEmailAssociationProvider
                );
            },
            $expected
        );

        $record = $this->getTransitionRecord();
        $user = new User();

        $this->manager->expects($expected ? $this->once() : $this->never())
            ->method('process')
            ->with(
                $expected,
                [
                    'transitionRecord' => $record,
                    'transitionUser' => $user
                ]
            );

        $this->event->expects($this->once())
            ->method('getTransitionRecord')
            ->willReturn($record);
        $this->event->expects($this->any())
            ->method('getTransitionUser')
            ->willReturn($user);
        $this->event->expects($this->once())
            ->method('stopPropagation');

        $this->handler->handle($this->event, $notifications);
    }

    public function handleDataProvider(): array
    {
        $notification1 = new EmailNotificationStub('unknown_workflow', self::TRANSITION_NAME);
        $notification2 = new EmailNotificationStub(self::WORKFLOW_NAME, 'unknown_transition');
        $notification3 = new EmailNotificationStub(self::WORKFLOW_NAME, self::TRANSITION_NAME);

        return [
            'no notifications' => [
                'notifications' => [],
                'expected' => [],
            ],
            'with notifications' => [
                'notifications' => [$notification1, $notification2, $notification3],
                'expected' => [$notification3],
            ]
        ];
    }

    public function testHandleNotSupportedNotification(): void
    {
        $this->manager->expects($this->never())
            ->method('process');

        $event = $this->createMock(NotificationEvent::class);
        $event->expects($this->never())
            ->method('stopPropagation');

        $this->handler->handle($event, []);
    }

    public function testHandleInvalidTransitionRecord(): void
    {
        $this->manager->expects($this->never())
            ->method('process')
            ->with($this->entity, []);

        $this->event->expects($this->once())
            ->method('getTransitionRecord')
            ->willReturn($this->getTransitionRecord());
        $this->event->expects($this->once())
            ->method('stopPropagation');

        $this->handler->handle($this->event, [new EmailNotificationStub()]);
    }

    private function getTransitionRecord(): WorkflowTransitionRecord
    {
        $workflowItem = $this->createMock(WorkflowItem::class);
        $workflowItem->expects($this->any())
            ->method('getWorkflowName')
            ->willReturn(self::WORKFLOW_NAME);

        $transitionRecord = $this->createMock(WorkflowTransitionRecord::class);
        $transitionRecord->expects($this->any())
            ->method('getTransitionName')
            ->willReturn(self::TRANSITION_NAME);
        $transitionRecord->expects($this->any())
            ->method('getWorkflowItem')
            ->willReturn($workflowItem);

        return $transitionRecord;
    }
}
