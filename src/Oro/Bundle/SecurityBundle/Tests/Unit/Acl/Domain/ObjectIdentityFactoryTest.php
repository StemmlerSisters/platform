<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain;

use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdentityFactory;
use Oro\Bundle\SecurityBundle\Acl\Exception\InvalidAclException;
use Oro\Bundle\SecurityBundle\Attribute\Acl as AclAttribute;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\Entity\TestEntity;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\Entity\TestEntityImplementsDomainObjectInterface;
use Oro\Bundle\SecurityBundle\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Acl\Exception\InvalidDomainObjectException;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ObjectIdentityFactoryTest extends TestCase
{
    private ObjectIdentityFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new ObjectIdentityFactory(
            TestHelper::get($this)->createAclExtensionSelector()
        );
    }

    public function testRoot(): void
    {
        $id = $this->factory->root('entity');
        $this->assertEquals('entity', $id->getIdentifier());
        $this->assertEquals(ObjectIdentityFactory::ROOT_IDENTITY_TYPE, $id->getType());

        $id = $this->factory->root(\stdClass::class);
        $this->assertEquals('stdclass', $id->getIdentifier());
        $this->assertEquals(ObjectIdentityFactory::ROOT_IDENTITY_TYPE, $id->getType());

        $id = $this->factory->root($this->factory->get('Entity:' . TestEntity::class));
        $this->assertEquals('entity', $id->getIdentifier());
        $this->assertEquals(ObjectIdentityFactory::ROOT_IDENTITY_TYPE, $id->getType());

        $id = $this->factory->root($this->factory->get(new TestEntity(123)));
        $this->assertEquals('entity', $id->getIdentifier());
        $this->assertEquals(ObjectIdentityFactory::ROOT_IDENTITY_TYPE, $id->getType());

        $id = $this->factory->root('action');
        $this->assertEquals('action', $id->getIdentifier());
        $this->assertEquals(ObjectIdentityFactory::ROOT_IDENTITY_TYPE, $id->getType());

        $id = $this->factory->root('Action');
        $this->assertEquals('action', $id->getIdentifier());
        $this->assertEquals(ObjectIdentityFactory::ROOT_IDENTITY_TYPE, $id->getType());

        $id = $this->factory->root($this->factory->get('Action: Some Action'));
        $this->assertEquals('action', $id->getIdentifier());
        $this->assertEquals(ObjectIdentityFactory::ROOT_IDENTITY_TYPE, $id->getType());
    }

    public function testUnderlyingForObjectLevelObjectIdentity(): void
    {
        $id = $this->createMock(ObjectIdentityInterface::class);
        $id->expects(self::any())
            ->method('getIdentifier')
            ->willReturn(123);
        $id->expects(self::any())
            ->method('getType')
            ->willReturn(TestEntity::class);

        $underlyingId = $this->factory->underlying($id);
        $this->assertEquals('entity', $underlyingId->getIdentifier());
        $this->assertEquals(TestEntity::class, $underlyingId->getType());
    }

    public function testUnderlyingForRootObjectIdentity(): void
    {
        $this->expectException(InvalidAclException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot get underlying ACL for ObjectIdentity(entity, %s)',
            ObjectIdentityFactory::ROOT_IDENTITY_TYPE
        ));

        $this->factory->underlying($this->factory->root('entity'));
    }

    public function testUnderlyingForClassLevelObjectIdentity(): void
    {
        $this->expectException(InvalidAclException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot get underlying ACL for ObjectIdentity(entity, %s)',
            TestEntity::class
        ));

        $this->factory->underlying($this->factory->get('entity:' . TestEntity::class));
    }

    public function testUnderlyingForClassLevelObjectIdentityThatDoesNotHaveToStringMethod(): void
    {
        $id = $this->createMock(ObjectIdentityInterface::class);
        $id->expects(self::any())
            ->method('getIdentifier')
            ->willReturn('entity');
        $id->expects(self::any())
            ->method('getType')
            ->willReturn(TestEntity::class);

        $this->expectException(InvalidAclException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot get underlying ACL for %s(entity, %s)',
            get_class($id),
            TestEntity::class
        ));

        $this->factory->underlying($id);
    }

    public function testFromDomainObjectPrefersInterfaceOverGetId(): void
    {
        $obj = new TestEntityImplementsDomainObjectInterface('getObjectIdentifier()');
        $id = $this->factory->get($obj);
        $this->assertEquals('getObjectIdentifier()', $id->getIdentifier());
        $this->assertEquals(get_class($obj), $id->getType());
    }

    public function testFromDomainObjectWithoutDomainObjectInterface(): void
    {
        $obj = new TestEntity('getId()');
        $id = $this->factory->get($obj);
        $this->assertEquals('getId()', $id->getIdentifier());
        $this->assertEquals(get_class($obj), $id->getType());
    }

    public function testFromDomainObjectNull(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->factory->get(null);
    }

    public function testGetShouldCatchInvalidArgumentException(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->factory->get(new TestEntityImplementsDomainObjectInterface());
    }

    /**
     * @dataProvider getProvider
     */
    public function testGet($descriptor, $expectedId, $expectedType): void
    {
        $id = $this->factory->get($descriptor);
        $this->assertEquals($expectedType, $id->getType());
        $this->assertEquals($expectedId, $id->getIdentifier());
    }

    public function testGetIncorrectClassDescriptor(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->factory->get('AcmeBundle\SomeClass');
    }

    public function testGetIncorrectEntityDescriptor(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->factory->get('AcmeBundle:SomeEntity');
    }

    public function testGetWithInvalidEntityName(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->factory->get('entity:AcmeBundle:Entity:SomeEntity');
    }

    public function testGetIncorrectActionDescriptor(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $this->factory->get('Some Action');
    }

    public function testFromEntityAclAttribute(): void
    {
        $obj = AclAttribute::fromArray(['id' => 'test', 'type' => 'entity', 'class' => 'Acme\SomeEntity']);
        $id = $this->factory->get($obj);
        $this->assertEquals('entity', $id->getIdentifier());
        $this->assertEquals('Acme\SomeEntity', $id->getType());
    }

    public function testFromActionAclAttribute(): void
    {
        $obj = AclAttribute::fromArray(['id' => 'test_action', 'type' => 'action']);
        $id = $this->factory->get($obj);
        $this->assertEquals('action', $id->getIdentifier());
        $this->assertEquals('test_action', $id->getType());
    }

    public static function getProvider(): array
    {
        return [
            'Entity'              => ['Entity:' . TestEntity::class, 'entity', TestEntity::class],
            'Entity (whitespace)' => ['Entity: ' . TestEntity::class, 'entity', TestEntity::class],
            'ENTITY'              => ['ENTITY:' . TestEntity::class, 'entity', TestEntity::class],
            'Action'              => ['Action:Some Action', 'action', 'Some Action'],
            'Action (whitespace)' => ['Action: Some Action', 'action', 'Some Action'],
            'ACTION'              => ['ACTION:Some Action', 'action', 'Some Action'],
        ];
    }
}
