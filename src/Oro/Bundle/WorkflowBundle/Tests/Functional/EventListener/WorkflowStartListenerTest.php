<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Functional\EventListener;

use Doctrine\ORM\Events;
use Oro\Bundle\TestFrameworkBundle\Entity\WorkflowAwareEntity;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowTransitionRecord;
use Oro\Bundle\WorkflowBundle\Tests\Functional\WorkflowTestCase;
use Oro\Component\Testing\Doctrine\StubEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @dbIsolationPerTest
 */
class WorkflowStartListenerTest extends WorkflowTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $this->loadFixtures([LoadUser::class]);
    }

    public function testPersistStarts()
    {
        $this->loadWorkflowFrom('/Tests/Functional/EventListener/DataFixtures/config/StartListenerActiveNotActive');

        $firstEntity = $this->createWorkflowAwareEntity();
        $systemWorkflowManager = $this->getSystemWorkflowManager();

        self::assertInstanceOf(
            WorkflowItem::class,
            $systemWorkflowManager->getWorkflowItem($firstEntity, 'test_flow_autostart')
        );
        self::assertNull(
            $systemWorkflowManager->getWorkflowItem($firstEntity, 'test_flow_autostart_not_active')
        );

        //test after activation
        $systemWorkflowManager->activateWorkflow('test_flow_autostart_not_active');

        $secondEntity = $this->createWorkflowAwareEntity();

        self::assertInstanceOf(
            WorkflowItem::class,
            $systemWorkflowManager->getWorkflowItem($secondEntity, 'test_flow_autostart')
        );
        self::assertInstanceOf(
            WorkflowItem::class,
            $systemWorkflowManager->getWorkflowItem($secondEntity, 'test_flow_autostart_not_active')
        );
    }

    public function testForceAutoStartDifferentApplications()
    {
        $this->loadWorkflowFrom('/Tests/Functional/EventListener/DataFixtures/config/StartListenerForceAutoStart');

        //making context of execution as default application
        self::getContainer()->get('security.token_storage')
            ->setToken(new UsernamePasswordToken($this->getReference(LoadUser::USER), 'user'));

        //testing in context of some request as application filters depends on requestStack
        self::getContainer()->get('event_dispatcher')
            ->addListener(KernelEvents::REQUEST, function () {
                $entity = $this->createWorkflowAwareEntity();
                $systemWorkflowManager = $this->getSystemWorkflowManager();

                //as default app
                self::assertInstanceOf(
                    WorkflowItem::class,
                    $systemWorkflowManager->getWorkflowItem($entity, 'test_flow_force_autostart_different_app')
                );

                self::assertInstanceOf(
                    WorkflowItem::class,
                    $systemWorkflowManager->getWorkflowItem($entity, 'test_flow_no_force_autostart_default_app')
                );

                self::assertNotInstanceOf(
                    WorkflowItem::class,
                    $systemWorkflowManager->getWorkflowItem($entity, 'test_flow_no_force_autostart_different_app')
                );
            });

        //open any page to fulfill requestStack
        $this->client->request('GET', $this->getUrl('oro_dashboard_index'));
    }

    public function testMassStartWorkflow()
    {
        $this->assertWorkflowItemsCount(0);
        $transitionRecords = $this->getEntityManager(WorkflowTransitionRecord::class)
            ->getRepository(WorkflowTransitionRecord::class)->findAll();

        $this->loadWorkflowFrom('/Tests/Functional/EventListener/DataFixtures/config/StartListenerMassAutoStart');

        for ($i = 0; $i < 10; $i++) {
            $this->createWorkflowAwareEntity(false);
        }

        $listenerMock = $this->createMock(StubEventListener::class);

        /**
         * One "postFlush" call for WorkflowAwareEntity
         * One "postFlush" call for WorkflowTransitionRecord
         * One "postFlush" call for WorkflowItem
         */
        $listenerMock->expects($this->exactly(3))
            ->method('postFlush');

        $entityManger = $this->getEntityManager(WorkflowAwareEntity::class);
        $eventManager = $entityManger->getEventManager();
        $eventManager->addEventListener(Events::postFlush, $listenerMock);

        $entityManger->flush();

        $this->assertWorkflowItemsCount(0, 'test_flow_autostart_three_inactive');
        $this->assertWorkflowItemsCount(10, 'test_flow_autostart_two');
        $this->assertWorkflowItemsCount(10, 'test_flow_autostart_one');
        $this->assertWorkflowItemsCount(20);
        $this->assertWorkflowTransitionRecordCount(20 + count($transitionRecords));
    }
}
