<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Persistence;

use Oro\Bundle\SecurityBundle\Acl\Exception\InvalidAclManagerException;
use Oro\Bundle\SecurityBundle\Acl\Persistence\AbstractAclManager;
use Oro\Bundle\SecurityBundle\Acl\Persistence\BaseAclManager;
use Oro\Bundle\SecurityBundle\Model\Role;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AbstractAclManagerTest extends TestCase
{
    private AbstractAclManager&MockObject $abstract;

    #[\Override]
    protected function setUp(): void
    {
        $this->abstract = $this->getMockForAbstractClass(AbstractAclManager::class);
    }

    public function testGetSid(): void
    {
        $manager = new BaseAclManager();
        $this->abstract->setBaseAclManager($manager);

        self::assertEquals(
            new RoleSecurityIdentity('ROLE_TEST'),
            $this->abstract->getSid('ROLE_TEST')
        );

        $src = $this->createMock(Role::class);
        $src->expects(self::once())
            ->method('getRole')
            ->willReturn('ROLE_TEST');

        self::assertEquals(
            new RoleSecurityIdentity('ROLE_TEST'),
            $this->abstract->getSid($src)
        );

        $src = $this->createMock(UserInterface::class);
        $src->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn('Test');
        self::assertEquals(
            new UserSecurityIdentity('Test', get_class($src)),
            $this->abstract->getSid($src)
        );

        $user = $this->createMock(UserInterface::class);
        $user->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn('Test');
        $src = $this->createMock(TokenInterface::class);
        $src->expects(self::once())
            ->method('getUser')
            ->willReturn($user);

        self::assertEquals(
            new UserSecurityIdentity('Test', get_class($user)),
            $this->abstract->getSid($src)
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->abstract->getSid(new \stdClass());
    }

    public function testNoBaseAclManager(): void
    {
        $this->expectException(InvalidAclManagerException::class);
        $this->abstract->getSid('ROLE_TEST');
    }
}
