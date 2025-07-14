<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\TestDomainObject;
use Oro\Bundle\SecurityBundle\Tests\Unit\Fixtures\Models\CMS\CmsComment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Acl\Exception\InvalidDomainObjectException;
use Symfony\Component\Security\Acl\Model\DomainObjectInterface;

class ObjectIdAccessorTest extends TestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
    }

    public function testGetIdPrefersInterfaceOverGetId(): void
    {
        $accessor = new ObjectIdAccessor($this->doctrineHelper);

        $obj = $this->createMock(DomainObjectInterface::class);
        $obj->expects($this->once())
            ->method('getObjectIdentifier')
            ->willReturn('getObjectIdentifier()');

        $id = $accessor->getId($obj);

        $this->assertEquals('getObjectIdentifier()', $id);
    }

    public function testGetIdWithoutDomainObjectInterface(): void
    {
        $accessor = new ObjectIdAccessor($this->doctrineHelper);

        $id = $accessor->getId(new TestDomainObject());
        $this->assertEquals('getId()', $id);
    }

    public function testGetIdNull(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $doctrineHelper = $this->createMock(DoctrineHelper::class);

        $accessor = new ObjectIdAccessor($doctrineHelper);

        $accessor->getId(null);
    }

    public function testGetIdNonValidObject(): void
    {
        $this->expectException(InvalidDomainObjectException::class);
        $accessor = new ObjectIdAccessor($this->doctrineHelper);
        $accessor->getId(new \stdClass());
    }

    public function testGetIdEntity(): void
    {
        $accessor = new ObjectIdAccessor($this->doctrineHelper);

        $object = new CmsComment();
        $object->setId(234);

        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with($object)
            ->willReturn(true);

        $this->doctrineHelper->expects($this->once())
            ->method('getSingleEntityIdentifier')
            ->with($object)
            ->willReturn($object->getIdentity());

        $this->assertEquals(234, $accessor->getId($object));
    }
}
