<?php

namespace Oro\Bundle\OrganizationBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class BusinessUnitTest extends TestCase
{
    private BusinessUnit $unit;

    #[\Override]
    protected function setUp(): void
    {
        $this->unit = new BusinessUnit();
    }

    public function testId(): void
    {
        $this->assertNull($this->unit->getId());
    }

    public function testName(): void
    {
        $name = 'test';
        $this->assertNull($this->unit->getName());
        $this->unit->setName($name);
        $this->assertEquals($name, $this->unit->getName());
        $this->assertEquals($name, (string)$this->unit);
    }

    public function testOrganization(): void
    {
        $organization = new Organization();
        $this->assertNull($this->unit->getOrganization());
        $this->unit->setOrganization($organization);
        $this->assertEquals($organization, $this->unit->getOrganization());
    }

    public function testPhone(): void
    {
        $phone = 911;
        $this->assertNull($this->unit->getPhone());
        $this->unit->setPhone($phone);
        $this->assertEquals($phone, $this->unit->getPhone());
    }

    public function testWebsite(): void
    {
        $site = 'http://test.com';
        $this->assertNull($this->unit->getWebsite());
        $this->unit->setWebsite($site);
        $this->assertEquals($site, $this->unit->getWebsite());
    }

    public function testEmail(): void
    {
        $mail = 'test@test.com';
        $this->assertNull($this->unit->getEmail());
        $this->unit->setEmail($mail);
        $this->assertEquals($mail, $this->unit->getEmail());
    }

    public function testFax(): void
    {
        $fax = '321';
        $this->assertNull($this->unit->getFax());
        $this->unit->setFax($fax);
        $this->assertEquals($fax, $this->unit->getFax());
    }

    public function testPrePersist(): void
    {
        $dateCreated = new \DateTime();
        $dateCreated = $dateCreated->format('yy');
        $this->assertNull($this->unit->getCreatedAt());
        $this->assertNull($this->unit->getUpdatedAt());
        $this->unit->prePersist();
        $this->assertEquals($dateCreated, $this->unit->getCreatedAt()->format('yy'));
        $this->assertEquals($dateCreated, $this->unit->getUpdatedAt()->format('yy'));
    }

    public function testUpdated(): void
    {
        $dateCreated = new \DateTime();
        $dateCreated = $dateCreated->format('yy');
        $this->assertNull($this->unit->getUpdatedAt());
        $this->unit->preUpdate();
        $this->assertEquals($dateCreated, $this->unit->getUpdatedAt()->format('yy'));
    }

    public function testUser(): void
    {
        $businessUnit = new BusinessUnit();
        $user = new User();

        $businessUnit->setUsers(new ArrayCollection([$user]));

        $this->assertContains($user, $businessUnit->getUsers());

        $businessUnit->removeUser($user);

        $this->assertNotContains($user, $businessUnit->getUsers());

        $businessUnit->addUser($user);

        $this->assertContains($user, $businessUnit->getUsers());
    }

    public function testOwners(): void
    {
        $entity = $this->unit;
        $businessUnit = new BusinessUnit();

        $this->assertEmpty($entity->getOwner());

        $entity->setOwner($businessUnit);

        $this->assertEquals($businessUnit, $entity->getOwner());
    }
}
