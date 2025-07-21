<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\Security;

use Doctrine\ORM\ORMInvalidArgumentException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Oro\Bundle\UserBundle\Security\UserProvider;
use Oro\Bundle\UserBundle\Tests\Unit\Fixture\RegularUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class UserProviderTest extends TestCase
{
    private const USER_CLASS = User::class;

    private UserLoaderInterface&MockObject $userLoader;
    private ManagerRegistry&MockObject $doctrine;
    private UserProvider $userProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->userLoader->expects(self::any())
            ->method('getUserClass')
            ->willReturn(self::USER_CLASS);

        $this->userProvider = new UserProvider($this->userLoader, $this->doctrine);
    }

    public function testLoadUserForExistingUserIdentifier(): void
    {
        $username = 'foobar';
        $user = $this->createMock(self::USER_CLASS);

        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn($user);

        self::assertSame(
            $user,
            $this->userProvider->loadUserByIdentifier($username)
        );
    }

    public function testLoadUserForNotExistingUserIdentifier(): void
    {
        $this->expectException(UserNotFoundException::class);
        $username = 'foobar';
        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn(null);

        $this->userProvider->loadUserByIdentifier($username);
    }

    public function testRefreshUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);
        $user = $this->createMock(self::USER_CLASS);
        $user->expects(self::any())
            ->method('getId')
            ->willReturn(123);

        $manager = $this->createMock(ObjectManager::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(self::USER_CLASS)
            ->willReturn($manager);
        $manager->expects(self::once())
            ->method('refresh')
            ->with($user)
            ->willThrowException(new ORMInvalidArgumentException('Not managed'));
        $manager->expects(self::once())
            ->method('find')
            ->with(self::USER_CLASS, $user->getId())
            ->willReturn(null);

        $this->userProvider->refreshUser($user);
    }

    public function testRefreshUserManaged(): void
    {
        $user = $this->createMock(self::USER_CLASS);
        $user->expects(self::any())
            ->method('getId')
            ->willReturn(123);

        $manager = $this->createMock(ObjectManager::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(self::USER_CLASS)
            ->willReturn($manager);
        $manager->expects(self::once())
            ->method('refresh')
            ->with(self::identicalTo($user));
        $manager->expects(self::never())
            ->method('find');

        $this->userProvider->refreshUser($user);
    }

    public function testRefreshManagedUser(): void
    {
        $user = $this->createMock(self::USER_CLASS);
        $user->expects(self::any())
            ->method('getId')
            ->willReturn(123);

        $manager = $this->createMock(ObjectManager::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(self::USER_CLASS)
            ->willReturn($manager);
        $manager->expects(self::once())
            ->method('refresh')
            ->with(self::identicalTo($user))
            ->willThrowException(new ORMInvalidArgumentException('Not managed'));
        $manager->expects(self::once())
            ->method('find')
            ->with(self::USER_CLASS, $user->getId())
            ->willReturn($user);

        $this->userProvider->refreshUser($user);
    }

    public function testRefreshUserNotOroUser(): void
    {
        $this->expectException(UnsupportedUserException::class);
        $this->expectExceptionMessage('Expected an instance of Oro\Bundle\UserBundle\Entity\User, but got');

        $user = $this->createMock(RegularUser::class);
        $this->userProvider->refreshUser($user);
    }

    public function testSupportsClassForSupportedUserObject(): void
    {
        $this->assertTrue($this->userProvider->supportsClass(self::USER_CLASS));
    }

    public function testSupportsClassForNotSupportedUserObject(): void
    {
        $this->assertFalse($this->userProvider->supportsClass(RegularUser::class));
    }
}
