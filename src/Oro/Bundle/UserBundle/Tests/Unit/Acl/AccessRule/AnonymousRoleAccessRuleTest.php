<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\Acl\AccessRule;

use Oro\Bundle\SecurityBundle\AccessRule\Criteria;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\Comparison;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\Path;
use Oro\Bundle\UserBundle\Acl\AccessRule\AnonymousRoleAccessRule;
use Oro\Bundle\UserBundle\Entity\Role;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class AnonymousRoleAccessRuleTest extends TestCase
{
    private AnonymousRoleAccessRule $accessRule;

    #[\Override]
    protected function setUp(): void
    {
        $this->accessRule = new AnonymousRoleAccessRule();
    }

    public function testIsApplicable(): void
    {
        $this->assertTrue($this->accessRule->isApplicable($this->createMock(Criteria::class)));
    }

    public function testProcess(): void
    {
        $criteria = new Criteria('ORM', Role::class, 'test');
        $this->accessRule->process($criteria);

        $this->assertEquals(
            new Comparison(new Path('role'), Comparison::NEQ, User::ROLE_ANONYMOUS),
            $criteria->getExpression()
        );
    }
}
