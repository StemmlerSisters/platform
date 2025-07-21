<?php

namespace Oro\Bundle\OrganizationBundle\Tests\Unit\Provider;

use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Provider\ScopeOrganizationCriteriaProvider;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationAwareTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ScopeOrganizationCriteriaProviderTest extends TestCase
{
    private TokenStorageInterface&MockObject $tokenStorage;
    private ScopeOrganizationCriteriaProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->provider = new ScopeOrganizationCriteriaProvider($this->tokenStorage);
    }

    public function testGetCriteriaField(): void
    {
        $this->assertEquals(ScopeOrganizationCriteriaProvider::ORGANIZATION, $this->provider->getCriteriaField());
    }

    public function testGetCriteriaValue(): void
    {
        $organization = new Organization();

        $token = $this->createMock(OrganizationAwareTokenInterface::class);
        $token->expects($this->once())
            ->method('getOrganization')
            ->willReturn($organization);

        $this->tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $this->assertSame($organization, $this->provider->getCriteriaValue());
    }

    public function testGetCriteriaValueWithoutToken(): void
    {
        $this->tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $this->assertNull($this->provider->getCriteriaValue());
    }

    public function testGetCriteriaValueWithoutOrganizationAwareToken(): void
    {
        $token = $this->createMock(TokenInterface::class);

        $this->tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $this->assertNull($this->provider->getCriteriaValue());
    }

    public function testGetCriteriaValueType(): void
    {
        $this->assertEquals(Organization::class, $this->provider->getCriteriaValueType());
    }
}
