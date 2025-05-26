<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Entity;

use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\EmailBundle\Model\FolderType;
use Oro\Bundle\EmailBundle\Tests\Unit\Fixtures\Entity\EmailAddress;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class EmailUserTest extends TestCase
{
    public function testGetterSetter(): void
    {
        $emailUser = new EmailUser();
        $email = new Email();
        $owner = new User();
        $organization = new Organization();
        $folder = new EmailFolder();
        $receivedAt = new \DateTime('now');

        $emailUser->setEmail($email);
        $emailUser->setOrganization($organization);
        $emailUser->addFolder($folder);
        $emailUser->setSeen(true);
        $emailUser->setOwner($owner);
        $emailUser->setReceivedAt($receivedAt);

        $this->assertEquals($email, $emailUser->getEmail());
        $this->assertEquals($organization, $emailUser->getOrganization());
        $this->assertEquals($folder, $emailUser->getFolders()->first());
        $this->assertEquals(true, $emailUser->isSeen());
        $this->assertEquals($owner, $emailUser->getOwner());
        $this->assertEquals($receivedAt, $emailUser->getReceivedAt());
        $this->assertNull($emailUser->getCreatedAt());

        $emailUser->setOrganization(null);
        $this->assertNull($emailUser->getOrganization());

        $this->assertFalse($emailUser->isEmailPrivate());
        $emailUser->setIsEmailPrivate(true);
        $this->assertTrue($emailUser->isEmailPrivate());
    }

    public function testBeforeSave(): void
    {
        $emailUser = new EmailUser();
        $emailUser->beforeSave();

        $this->assertInstanceOf(\DateTime::class, $emailUser->getCreatedAt());
    }

    /**
     * @dataProvider outgoingEmailUserProvider
     */
    public function testIsOutgoing(EmailUser $emailUser): void
    {
        $this->assertTrue($emailUser->isOutgoing());
        $this->assertFalse($emailUser->isIncoming());
    }

    public function outgoingEmailUserProvider(): array
    {
        $user = new User();

        return [
            'sent folder' => [
                (new EmailUser())
                    ->addFolder(
                        (new EmailFolder())
                            ->setType(FolderType::SENT)
                    )
            ],
            'drafts folder' => [
                (new EmailUser())
                    ->addFolder(
                        (new EmailFolder())
                            ->setType(FolderType::DRAFTS)
                    )
            ],
            'owner is sender' => [
                (new EmailUser())
                    ->setOwner($user)
                    ->setEmail(
                        (new Email())
                            ->setFromEmailAddress(
                                (new EmailAddress())
                                    ->setOwner($user)
                            )
                    )
                    ->addFolder(
                        (new EmailFolder())
                            ->setType(FolderType::OTHER)
                    )
            ],
        ];
    }

    /**
     * @dataProvider incomingEmailUserProvider
     */
    public function testIsIncoming(EmailUser $emailUser): void
    {
        $this->assertTrue($emailUser->isIncoming());
        $this->assertFalse($emailUser->isOutgoing());
    }

    public function incomingEmailUserProvider(): array
    {
        $user = new User();
        $user->setId(1);
        $user2 = new User();
        $user->setId(2);

        return [
            'inbox folder' => [
                (new EmailUser())
                    ->addFolder(
                        (new EmailFolder())
                            ->setType(FolderType::INBOX)
                    )
            ],
            'spam folder' => [
                (new EmailUser())
                    ->addFolder(
                        (new EmailFolder())
                            ->setType(FolderType::SPAM)
                    )
            ],
            'owner is not sender' => [
                (new EmailUser())
                    ->setOwner($user)
                    ->setEmail(
                        (new Email())
                            ->setFromEmailAddress(
                                (new EmailAddress())
                                    ->setOwner($user2)
                            )
                    )
                    ->addFolder(
                        (new EmailFolder())
                            ->setType(FolderType::OTHER)
                    )
            ],
        ];
    }

    /**
     * @dataProvider incomingAndOutgoingProvider
     */
    public function testIsIncomingAndOutgoing(EmailUser $emailUser): void
    {
        $this->assertTrue($emailUser->isIncoming());
        $this->assertTrue($emailUser->isOutgoing());
    }

    public function incomingAndOutgoingProvider(): array
    {
        return [
            'inbox and sent folders' => [
                (new EmailUser())
                    ->addFolder(
                        (new EmailFolder())
                            ->setType(FolderType::INBOX)
                    )
                    ->addFolder(
                        (new EmailFolder())
                            ->setType(FolderType::SENT)
                    )
            ],
        ];
    }
}
