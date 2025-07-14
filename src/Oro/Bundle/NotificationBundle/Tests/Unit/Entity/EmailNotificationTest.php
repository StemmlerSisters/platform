<?php

namespace Oro\Bundle\NotificationBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\EmailBundle\Entity\EmailTemplate;
use Oro\Bundle\NotificationBundle\Entity\EmailNotification;
use Oro\Bundle\NotificationBundle\Entity\RecipientList;
use Oro\Bundle\UserBundle\Entity\Group;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class EmailNotificationTest extends TestCase
{
    private EmailNotification $entity;

    #[\Override]
    protected function setUp(): void
    {
        $this->entity = new EmailNotification();

        // get id should return null cause this entity was not loaded from DB
        $this->assertNull($this->entity->getId());
    }

    public function testGetterSetterForEntityName(): void
    {
        $this->assertNull($this->entity->getEntityName());
        $this->entity->setEntityName('testName');
        $this->assertEquals('testName', $this->entity->getEntityName());
    }

    public function testGetterSetterForTemplate(): void
    {
        $emailTemplate = $this->createMock(EmailTemplate::class);
        $this->assertNull($this->entity->getTemplate());
        $this->entity->setTemplate($emailTemplate);
        $this->assertEquals($emailTemplate, $this->entity->getTemplate());
    }

    public function testGetterSetterForEvent(): void
    {
        $this->assertNull($this->entity->getEventName());

        $this->entity->setEventName('test.name');
        $this->assertEquals('test.name', $this->entity->getEventName());
    }

    public function testGetterSetterForRecipients(): void
    {
        $this->assertNull($this->entity->getRecipientList());

        $list = $this->createMock(RecipientList::class);
        $this->entity->setRecipientList($list);
        $this->assertEquals($list, $this->entity->getRecipientList());
    }

    public function testGetUsersRecipientsList(): void
    {
        $this->assertTrue($this->entity->getRecipientUsersList()->isEmpty());

        $userMock1 = $this->createMock(User::class);
        $userMock2 = $this->createMock(User::class);
        $collection = new ArrayCollection([$userMock1, $userMock2]);

        $list = $this->createMock(RecipientList::class);
        $list->expects($this->once())
            ->method('getUsers')
            ->willReturn($collection);
        $this->entity->setRecipientList($list);

        $actual = $this->entity->getRecipientUsersList();
        $this->assertInstanceOf(ArrayCollection::class, $actual);
        $this->assertEquals($collection, $actual);
    }

    public function testGetGroupsRecipientsList(): void
    {
        $this->assertTrue($this->entity->getRecipientGroupsList()->isEmpty());

        $groupMock1 = $this->createMock(Group::class);
        $groupMock2 = $this->createMock(Group::class);

        $collection = new ArrayCollection([$groupMock1, $groupMock2]);

        $list = $this->createMock(RecipientList::class);
        $list->expects($this->once())
            ->method('getGroups')
            ->willReturn($collection);
        $this->entity->setRecipientList($list);

        $actual = $this->entity->getRecipientGroupsList();
        $this->assertInstanceOf(ArrayCollection::class, $actual);
        $this->assertEquals($collection, $actual);
    }
}
