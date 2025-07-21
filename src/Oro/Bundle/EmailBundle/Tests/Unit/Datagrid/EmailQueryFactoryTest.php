<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Datagrid;

use Oro\Bundle\EmailBundle\Datagrid\EmailQueryFactory;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\Manager\MailboxManager;
use Oro\Bundle\EmailBundle\Entity\Provider\EmailOwnerProviderInterface;
use Oro\Bundle\EmailBundle\Entity\Provider\EmailOwnerProviderStorage;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\FilterBundle\Filter\FilterUtility;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Component\Testing\Unit\ORM\OrmTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormFactoryInterface;

class EmailQueryFactoryTest extends OrmTestCase
{
    private const string JOIN_ALIAS = 'a';
    private const string TEST_ENTITY = User::class;
    private const string TEST_NAME_DQL_FORMATTED = 'CONCAT(a.firstName, CONCAT(a.lastName, \'\'))';

    private EmailOwnerProviderStorage $providerStorage;
    private EntityNameResolver&MockObject $entityNameResolver;
    private TokenAccessorInterface&MockObject $tokenAccessor;
    private MailboxManager&MockObject $mailboxManager;
    private EmailQueryFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->providerStorage = new EmailOwnerProviderStorage();
        $this->entityNameResolver = $this->createMock(EntityNameResolver::class);
        $this->mailboxManager = $this->createMock(MailboxManager::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);

        $this->factory = new EmailQueryFactory(
            $this->providerStorage,
            $this->entityNameResolver,
            $this->mailboxManager,
            $this->tokenAccessor,
            $this->createMock(FormFactoryInterface::class),
            new FilterUtility()
        );
    }

    public function testAddFromEmailAddressWithoutProviders(): void
    {
        $em = $this->getTestEntityManager();
        $qb = $em->createQueryBuilder();
        $qb->select('e')
            ->from(Email::class, 'e')
            ->leftJoin('e.fromEmailAddress', self::JOIN_ALIAS);

        $this->factory->addFromEmailAddress($qb);
        $this->assertEquals(
            'SELECT e, NULLIF(\'\', \'\') AS fromEmailAddressOwnerClass,'
            . ' NULLIF(0, 0) AS fromEmailAddressOwnerId, a.email AS fromEmailAddress'
            . ' FROM Oro\Bundle\EmailBundle\Entity\Email e LEFT JOIN e.fromEmailAddress a',
            $qb->getDQL()
        );
    }

    public function testAddFromEmailAddressOneProviderGiven(): void
    {
        $provider = $this->createMock(EmailOwnerProviderInterface::class);
        $provider->expects($this->any())
            ->method('getEmailOwnerClass')
            ->willReturn(self::TEST_ENTITY);
        $this->providerStorage->addProvider($provider);

        $this->entityNameResolver->expects($this->once())
            ->method('getNameDQL')
            ->with(self::TEST_ENTITY, 'owner1')
            ->willReturn(self::TEST_NAME_DQL_FORMATTED);
        $em = $this->getTestEntityManager();
        $qb = $em->createQueryBuilder();
        $qb->select('e')
            ->from(Email::class, 'e')
            ->leftJoin('e.fromEmailAddress', self::JOIN_ALIAS);

        $this->factory->addFromEmailAddress($qb);

        $this->assertEquals(
            'SELECT e,'
            . ' (CASE'
            . ' WHEN a.owner1 IS NOT NULL THEN \'Oro\Bundle\UserBundle\Entity\User\''
            . ' ELSE NULLIF(\'\', \'\') END) AS fromEmailAddressOwnerClass,'
            . ' COALESCE(IDENTITY(a.owner1) ) AS fromEmailAddressOwnerId,'
            . ' CONCAT(\'\','
            . ' CASE WHEN a.hasOwner = true THEN (CASE'
            . ' WHEN a.owner1 IS NOT NULL THEN CONCAT(a.firstName, CONCAT(a.lastName, \'\'))'
            . ' ELSE \'\' END) ELSE a.email END) AS fromEmailAddress'
            . ' FROM Oro\Bundle\EmailBundle\Entity\Email e LEFT JOIN e.fromEmailAddress a LEFT JOIN a.owner1 owner1',
            $qb->getDQL()
        );
    }

    public function testFilterQueryByUserIdWhenMailboxesAreFound(): void
    {
        $user = new User();
        $organization = new Organization();

        $em = $this->getTestEntityManager();
        $qb = $em->createQueryBuilder();

        $this->tokenAccessor->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->tokenAccessor->expects($this->exactly(2))
            ->method('getOrganization')
            ->willReturn($organization);

        $this->mailboxManager->expects($this->any())
            ->method('findAvailableMailboxIds')
            ->with($user, $organization)
            ->willReturn([1, 3, 5]);

        $qb->select('eu')
            ->from('EmailUser', 'eu');

        $this->factory->applyAcl($qb);

        $this->assertEquals(
            'SELECT eu FROM EmailUser eu'
            . ' WHERE (eu.owner = :owner AND eu.organization  = :organization) OR eu.mailboxOwner IN(:mailboxIds)',
            $qb->getQuery()->getDQL()
        );
    }

    public function testFilterQueryByUserIdWhenNoMailboxesFound(): void
    {
        $user = new User();
        $organization = new Organization();

        $em = $this->getTestEntityManager();
        $qb = $em->createQueryBuilder();

        $this->tokenAccessor->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->tokenAccessor->expects($this->exactly(2))
            ->method('getOrganization')
            ->willReturn($organization);

        $this->mailboxManager->expects($this->any())
            ->method('findAvailableMailboxIds')
            ->with($user, $organization)
            ->willReturn([1, 3, 5]);

        $qb->select('eu')
            ->from('EmailUser', 'eu');

        $this->factory->applyAcl($qb);

        $this->assertEquals(
            'SELECT eu FROM EmailUser eu'
            . ' WHERE (eu.owner = :owner AND eu.organization  = :organization) OR eu.mailboxOwner IN(:mailboxIds)',
            $qb->getQuery()->getDQL()
        );
    }
}
