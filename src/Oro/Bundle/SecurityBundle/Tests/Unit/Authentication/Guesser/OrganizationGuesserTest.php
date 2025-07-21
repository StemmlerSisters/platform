<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Authentication\Guesser;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\Guesser\OrganizationGuesser;
use Oro\Bundle\SecurityBundle\Exception\BadUserOrganizationException;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class OrganizationGuesserTest extends TestCase
{
    private OrganizationGuesser $guesser;

    #[\Override]
    protected function setUp(): void
    {
        $this->guesser = new OrganizationGuesser();
    }

    public function testGuessFromUserOrganization(): void
    {
        $user = $this->createMock(User::class);
        $organization = $this->createMock(Organization::class);
        $user->expects($this->once())
            ->method('isBelongToOrganization')
            ->with($this->identicalTo($organization), $this->equalTo(true))
            ->willReturn(false);
        $user->expects($this->once())
            ->method('getOrganization')
            ->willReturn($organization);
        $user->expects($this->once())
            ->method('getOrganizations')
            ->with($this->isTrue())
            ->willReturn(new ArrayCollection([$organization]));

        $this->assertSame($organization, $this->guesser->guess($user));
    }

    public function testGuessFromUserOrganizations(): void
    {
        $user = $this->createMock(User::class);
        $organization = $this->createMock(Organization::class);
        $organization1 = $this->createMock(Organization::class);
        $organization2 = $this->createMock(Organization::class);
        $user->expects($this->once())
            ->method('getOrganization')
            ->willReturn($organization);
        $user->expects($this->once())
            ->method('isBelongToOrganization')
            ->with($this->identicalTo($organization), $this->equalTo(true))
            ->willReturn(false);
        $user->expects($this->once())
            ->method('getOrganizations')
            ->with($this->isTrue())
            ->willReturn(new ArrayCollection([$organization1, $organization2]));

        $this->assertSame($organization1, $this->guesser->guess($user));
    }

    public function testUserWithoutOrganizationGuess(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('getOrganization')
            ->willReturn(null);

        self::expectException(BadUserOrganizationException::class);
        self::expectExceptionMessage('The user does not have an active organization assigned to it.');

        $this->guesser->guess($user);
    }

    public function testGuessFromUserOrganizationsWhenTheyAreEmpty(): void
    {
        $user = $this->createMock(User::class);
        $userOrganization = $this->createMock(Organization::class);
        $user->expects($this->once())
            ->method('getOrganization')
            ->willReturn($userOrganization);
        $user->expects($this->once())
            ->method('getOrganizations')
            ->with($this->isTrue())
            ->willReturn(new ArrayCollection());

        self::expectException(BadUserOrganizationException::class);
        self::expectExceptionMessage('The user does not have active organization assigned to it.');

        $this->guesser->guess($user);
    }
}
