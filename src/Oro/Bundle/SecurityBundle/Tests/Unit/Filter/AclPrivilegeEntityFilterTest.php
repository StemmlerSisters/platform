<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Filter;

use Oro\Bundle\SecurityBundle\Filter\AclPrivilegeEntityFilter;
use Oro\Bundle\SecurityBundle\Model\AclPermission;
use Oro\Bundle\SecurityBundle\Model\AclPrivilege;
use Oro\Bundle\SecurityBundle\Model\AclPrivilegeIdentity;
use Oro\Bundle\SecurityBundle\Model\ConfigurablePermission;
use PHPUnit\Framework\TestCase;

class AclPrivilegeEntityFilterTest extends TestCase
{
    private AclPrivilegeEntityFilter $filter;

    #[\Override]
    protected function setUp(): void
    {
        $this->filter = new AclPrivilegeEntityFilter();
    }

    /**
     * @dataProvider isSupportedAclPrivilegeProvider
     */
    public function testIsSupported(AclPrivilege $aclPrivilege, bool $isSupported): void
    {
        $this->assertSame($isSupported, $this->filter->isSupported($aclPrivilege));
    }

    public function isSupportedAclPrivilegeProvider(): array
    {
        return [
            'supported' => [
                'aclPrivilege' => (new AclPrivilege())->setIdentity(new AclPrivilegeIdentity('entity:test')),
                'isSupported' => true
            ],
            'not supported' => [
                'aclPrivilege' => (new AclPrivilege())->setIdentity(new AclPrivilegeIdentity('config:test')),
                'isSupported' => false
            ]
        ];
    }

    public function testFilter(): void
    {
        $aclPrivilege1 = (new AclPrivilege())->setIdentity(new AclPrivilegeIdentity('entity:test1'));
        $aclPrivilege2 = (new AclPrivilege())->setIdentity(new AclPrivilegeIdentity('entity:test2'));

        $aclPrivilege1->addPermission(new AclPermission('perm1'));
        $aclPrivilege1->addPermission(new AclPermission('perm2'));
        $aclPrivilege2->addPermission(new AclPermission('perm2'));

        $configurablePermission = $this->createMock(ConfigurablePermission::class);
        $configurablePermission->expects($this->any())
            ->method('isEntityPermissionConfigurable')
            ->willReturnMap([
                ['test1','perm1', false],
                ['test1','perm2', true],
                ['test2','perm2', false]
            ]);

        $this->assertTrue($this->filter->filter($aclPrivilege1, $configurablePermission));
        $this->assertCount(1, $aclPrivilege1->getPermissions());
        $this->assertFalse($this->filter->filter($aclPrivilege2, $configurablePermission));
    }
}
