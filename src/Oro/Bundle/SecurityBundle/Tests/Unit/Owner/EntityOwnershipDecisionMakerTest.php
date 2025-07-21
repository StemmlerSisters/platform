<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Owner;

use Doctrine\Inflector\Rules\English\InflectorFactory;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\SecurityBundle\Owner\EntityOwnerAccessor;
use Oro\Bundle\SecurityBundle\Owner\EntityOwnershipDecisionMaker;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadata;
use Oro\Bundle\SecurityBundle\Owner\OwnerTree;
use Oro\Bundle\SecurityBundle\Owner\OwnerTreeProvider;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\Entity\BusinessUnit;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\Entity\Organization;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\Entity\TestEntity;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\Entity\User;
use Oro\Bundle\SecurityBundle\Tests\Unit\Stub\OwnershipMetadataProviderStub;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Security\Acl\Exception\InvalidDomainObjectException;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class EntityOwnershipDecisionMakerTest extends AbstractCommonEntityOwnershipDecisionMakerTest
{
    protected const USER_ID = 505;

    private TokenAccessorInterface&MockObject $tokenAccessor;
    private OwnershipMetadataProviderStub $metadataProvider;
    private EntityOwnershipDecisionMaker $decisionMaker;

    #[\Override]
    protected function setUp(): void
    {
        $this->tree = new OwnerTree();

        $this->metadataProvider = new OwnershipMetadataProviderStub($this);
        $this->metadataProvider->setMetadata(
            $this->metadataProvider->getOrganizationClass(),
            new OwnershipMetadata()
        );
        $this->metadataProvider->setMetadata(
            $this->metadataProvider->getBusinessUnitClass(),
            new OwnershipMetadata('BUSINESS_UNIT', 'owner', 'owner_id', 'organization')
        );
        $this->metadataProvider->setMetadata(
            $this->metadataProvider->getUserClass(),
            new OwnershipMetadata('BUSINESS_UNIT', 'owner', 'owner_id', 'organization')
        );
        $this->metadataProvider->getCacheMock()->expects(self::any())
            ->method('get')
            ->willReturn(true);

        $treeProvider = $this->createMock(OwnerTreeProvider::class);
        $treeProvider->expects($this->any())
            ->method('getTree')
            ->willReturn($this->tree);

        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);

        $doctrineHelper = $this->createMock(DoctrineHelper::class);

        $this->decisionMaker = new EntityOwnershipDecisionMaker(
            $treeProvider,
            new ObjectIdAccessor($doctrineHelper),
            new EntityOwnerAccessor($this->metadataProvider, (new InflectorFactory())->build()),
            $this->metadataProvider,
            $this->tokenAccessor
        );
    }

    public function testIsOrganization(): void
    {
        $this->assertFalse($this->decisionMaker->isOrganization(null));
        $this->assertFalse($this->decisionMaker->isOrganization('test'));
        $this->assertFalse($this->decisionMaker->isOrganization(new User()));
        $this->assertTrue($this->decisionMaker->isOrganization(new Organization()));
        $this->assertTrue($this->decisionMaker->isOrganization($this->createMock(Organization::class)));
    }

    public function testIsBusinessUnit(): void
    {
        $this->assertFalse($this->decisionMaker->isBusinessUnit(null));
        $this->assertFalse($this->decisionMaker->isBusinessUnit('test'));
        $this->assertFalse($this->decisionMaker->isBusinessUnit(new User()));
        $this->assertTrue($this->decisionMaker->isBusinessUnit(new BusinessUnit()));
        $this->assertTrue($this->decisionMaker->isBusinessUnit($this->createMock(BusinessUnit::class)));
    }

    public function testIsUser(): void
    {
        $this->assertFalse($this->decisionMaker->isUser(null));
        $this->assertFalse($this->decisionMaker->isUser('test'));
        $this->assertFalse($this->decisionMaker->isUser(new BusinessUnit()));
        $this->assertTrue($this->decisionMaker->isUser(new User()));
        $this->assertTrue($this->decisionMaker->isUser($this->createMock(User::class)));
    }

    public function testIsAssociatedWithOrganizationNullUser(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->decisionMaker->isAssociatedWithOrganization(null, null);
    }

    public function testIsAssociatedWithOrganizationNullObject(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $user = new User(self::USER_ID);
        $this->decisionMaker->isAssociatedWithOrganization($user, null);
    }

    public function testIsAssociatedWithBusinessUnitNullUser(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->decisionMaker->isAssociatedWithBusinessUnit(null, null);
    }

    public function testIsAssociatedWithBusinessUnitNullObject(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $user = new User(self::USER_ID);
        $this->decisionMaker->isAssociatedWithBusinessUnit($user, null);
    }

    public function testIsAssociatedWithUserNullUser(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->decisionMaker->isAssociatedWithUser(null, null);
    }

    public function testIsAssociatedWithUserNullObject(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $user = new User(self::USER_ID);
        $this->decisionMaker->isAssociatedWithUser($user, null);
    }

    public function testIsAssociatedWithOrganizationForSystemObject(): void
    {
        $user = new User(self::USER_ID);
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($user, new \stdClass()));
    }

    public function testIsAssociatedWithBusinessUnitForSystemObject(): void
    {
        $user = new User(self::USER_ID);
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($user, new \stdClass()));
    }

    public function testIsAssociatedWithUserForSystemObject(): void
    {
        $user = new User(self::USER_ID);
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($user, new \stdClass()));
    }

    public function testIsAssociatedWithOrganizationForOrganizationObject(): void
    {
        $this->buildTestTree();

        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user1, $this->org1));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user2, $this->org2));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user3, $this->org3));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user31, $this->org3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $this->org3));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $this->org4));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user411, $this->org4));
    }

    public function testIsAssociatedWithOrganizationForUserObject(): void
    {
        $this->buildTestTree();

        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user1, $this->user1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user2, $this->user2));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user3, $this->user3));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user3, $this->user31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user31, $this->user31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $this->user4));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $this->user3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $this->user31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $this->user411));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user411, $this->user411));
    }

    public function testIsAssociatedWithOrganizationForOrganizationOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('ORGANIZATION', 'owner', 'owner_id')
        );

        $obj = new TestEntity(1);
        $obj1 = new TestEntity(1, $this->org1);
        $obj2 = new TestEntity(1, $this->org2);
        $obj3 = new TestEntity(1, $this->org3);
        $obj4 = new TestEntity(1, $this->org4);

        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user1, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user2, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user3, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user3, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj3));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj4));
    }

    public function testIsAssociatedWithOrganizationForBusinessUnitOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('BUSINESS_UNIT', 'owner', 'owner_id', 'organization')
        );

        $obj = new TestEntity(1, null, $this->org1);
        $obj1 = new TestEntity(1, $this->bu1, $this->org1);
        $obj2 = new TestEntity(1, $this->bu2, $this->org2);
        $obj3 = new TestEntity(1, $this->bu3, $this->org3);
        $obj31 = new TestEntity(1, $this->bu31, $this->org3);
        $obj4 = new TestEntity(1, $this->bu4, $this->org4);
        $obj41 = new TestEntity(1, $this->bu41, $this->org4);
        $obj411 = new TestEntity(1, $this->bu411, $this->org4);

        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user1, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user2, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user3, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user3, $obj3));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user3, $obj31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj4));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj41));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj411));
    }

    public function testIsAssociatedWithOrganizationForUserOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('USER', 'owner', 'owner_id', 'organization')
        );

        $obj = new TestEntity(1, null, $this->org1);
        $obj1 = new TestEntity(1, $this->user1, $this->org1);
        $obj2 = new TestEntity(1, $this->user2, $this->org2);
        $obj3 = new TestEntity(1, $this->user3, $this->org3);
        $obj31 = new TestEntity(1, $this->user31, $this->org3);
        $obj4 = new TestEntity(1, $this->user4, $this->org4);
        $obj411 = new TestEntity(1, $this->user411, $this->org4);

        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user1, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user2, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user3, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user3, $obj3));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user3, $obj31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj4));
        $this->assertTrue($this->decisionMaker->isAssociatedWithOrganization($this->user4, $obj411));
    }

    public function testIsAssociatedWithBusinessUnitForOrganizationObject(): void
    {
        $this->buildTestTree();

        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $this->org1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $this->org2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $this->org3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->org3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->org4));
    }

    public function testIsAssociatedWithBusinessUnitForBusinessUnitObject(): void
    {
        $this->buildTestTree();

        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $this->bu1));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $this->bu2));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $this->bu3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu3, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu31, true));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu4));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu4, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu41));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu41, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu411));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->bu411, true));
    }

    public function testIsAssociatedWithBusinessUnitForUserObject(): void
    {
        $this->buildTestTree();

        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $this->user1));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $this->user2));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $this->user3));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $this->user3, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $this->user31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $this->user31, true));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user31, $this->user31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user31, $this->user31, true));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->user4));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->user4, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->user3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->user3, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->user31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->user31, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->user411));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $this->user411, true));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user411, $this->user411));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user411, $this->user411, true));
    }

    public function testIsAssociatedWithBusinessUnitForOrganizationOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('ORGANIZATION', 'owner', 'owner_id')
        );

        $obj = new TestEntity(1);
        $obj1 = new TestEntity(1, $this->org1);
        $obj2 = new TestEntity(1, $this->org2);
        $obj3 = new TestEntity(1, $this->org3);
        $obj4 = new TestEntity(1, $this->org4);

        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj4));
    }

    public function testIsAssociatedWithBusinessUnitForBusinessUnitOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('BUSINESS_UNIT', 'owner', 'owner_id', 'organization')
        );

        $obj = new TestEntity(1, null, $this->org1);
        $obj1 = new TestEntity(1, $this->bu1, $this->org1);
        $obj2 = new TestEntity(1, $this->bu2, $this->org2);
        $obj3 = new TestEntity(1, $this->bu3, $this->org3);
        $obj31 = new TestEntity(1, $this->bu31, $this->org3);
        $obj4 = new TestEntity(1, $this->bu4, $this->org4);
        $obj41 = new TestEntity(1, $this->bu41, $this->org4);
        $obj411 = new TestEntity(1, $this->bu411, $this->org4);

        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj3, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj31, true));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj4));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj4, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj41));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj41, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj411));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj411, true));
    }

    public function testIsAssociatedWithBusinessUnitForUserOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('USER', 'owner', 'owner_id', 'organization')
        );

        $obj = new TestEntity(1, null, $this->org1);
        $obj1 = new TestEntity(1, $this->user1, $this->org1);
        $obj2 = new TestEntity(1, $this->user2, $this->org2);
        $obj3 = new TestEntity(1, $this->user3, $this->org3);
        $obj31 = new TestEntity(1, $this->user31, $this->org3);
        $obj4 = new TestEntity(1, $this->user4, $this->org4);
        $obj411 = new TestEntity(1, $this->user411, $this->org4);

        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user3, $obj31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj3, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj31, true));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj4));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj4, true));
        $this->assertFalse($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj411));
        $this->assertTrue($this->decisionMaker->isAssociatedWithBusinessUnit($this->user4, $obj411, true));
    }

    public function testIsAssociatedWithUserForUserObject(): void
    {
        $this->buildTestTree();

        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user1, $this->user1));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user2, $this->user2));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user3, $this->user3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user3, $this->user31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user31, $this->user31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user4, $this->user4));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $this->user3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $this->user411));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user411, $this->user411));
    }

    public function testIsAssociatedWithUserForOrganizationOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('ORGANIZATION', 'owner', 'owner_id')
        );

        $obj = new TestEntity(1);
        $obj1 = new TestEntity(1, $this->org1);
        $obj2 = new TestEntity(1, $this->org2);
        $obj3 = new TestEntity(1, $this->org3);
        $obj4 = new TestEntity(1, $this->org4);

        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user1, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user2, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user3, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user3, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj4));
    }

    public function testIsAssociatedWithUserForBusinessUnitOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('BUSINESS_UNIT', 'owner', 'owner_id')
        );

        $obj = new TestEntity(1);
        $obj1 = new TestEntity(1, $this->bu1);
        $obj2 = new TestEntity(1, $this->bu2);
        $obj3 = new TestEntity(1, $this->bu3);
        $obj31 = new TestEntity(1, $this->bu31);
        $obj4 = new TestEntity(1, $this->bu4);
        $obj41 = new TestEntity(1, $this->bu41);
        $obj411 = new TestEntity(1, $this->bu411);

        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user1, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user2, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user3, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user3, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user3, $obj31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj4));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj41));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj411));
    }

    public function testIsAssociatedWithUserForUserOwnedObject(): void
    {
        $this->buildTestTree();

        $this->metadataProvider->setMetadata(
            TestEntity::class,
            new OwnershipMetadata('USER', 'owner', 'owner_id')
        );

        $obj = new TestEntity(1);
        $obj1 = new TestEntity(1, $this->user1);
        $obj2 = new TestEntity(1, $this->user2);
        $obj3 = new TestEntity(1, $this->user3);
        $obj31 = new TestEntity(1, $this->user31);
        $obj4 = new TestEntity(1, $this->user4);
        $obj411 = new TestEntity(1, $this->user411);

        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user1, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user1, $obj1));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user2, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user2, $obj2));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user3, $obj));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user3, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user3, $obj31));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj3));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj31));
        $this->assertTrue($this->decisionMaker->isAssociatedWithUser($this->user4, $obj4));
        $this->assertFalse($this->decisionMaker->isAssociatedWithUser($this->user4, $obj411));
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupports(?object $user, bool $expectedResult): void
    {
        $this->tokenAccessor->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->assertEquals($expectedResult, $this->decisionMaker->supports());
    }

    public function supportsDataProvider(): array
    {
        return [
            'without user' => [
                self::USER_ID => null,
                'expectedResult' => false,
            ],
            'unsupported user' => [
                self::USER_ID => new \stdClass(),
                'expectedResult' => false,
            ],
            'supported user' => [
                self::USER_ID => new User(),
                'expectedResult' => true,
            ],
        ];
    }
}
