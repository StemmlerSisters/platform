<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Model;

use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Model\AclPermission;
use PHPUnit\Framework\TestCase;

class AclPermissionTest extends TestCase
{
    public function testAclPermission(): void
    {
        $obj = new AclPermission('TestName', AccessLevel::BASIC_LEVEL);
        $this->assertEquals('TestName', $obj->getName());
        $this->assertEquals(AccessLevel::BASIC_LEVEL, $obj->getAccessLevel());

        $obj->setName('AnotherName');
        $obj->setAccessLevel(AccessLevel::GLOBAL_LEVEL);
        $this->assertEquals('AnotherName', $obj->getName());
        $this->assertEquals(AccessLevel::GLOBAL_LEVEL, $obj->getAccessLevel());
    }
}
