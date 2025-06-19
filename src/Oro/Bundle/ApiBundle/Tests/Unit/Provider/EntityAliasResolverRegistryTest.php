<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Provider;

use Oro\Bundle\ApiBundle\Provider\EntityAliasResolverRegistry;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class EntityAliasResolverRegistryTest extends TestCase
{
    private EntityAliasResolver&MockObject $defaultResolver;
    private EntityAliasResolver&MockObject $firstResolver;
    private EntityAliasResolver&MockObject $secondResolver;
    private ContainerInterface&MockObject $container;

    #[\Override]
    protected function setUp(): void
    {
        $this->defaultResolver = $this->createMock(EntityAliasResolver::class);
        $this->firstResolver = $this->createMock(EntityAliasResolver::class);
        $this->secondResolver = $this->createMock(EntityAliasResolver::class);
        $this->container = $this->createMock(ContainerInterface::class);
    }

    private function getRegistry(array $entityAliasResolvers): EntityAliasResolverRegistry
    {
        return new EntityAliasResolverRegistry(
            $entityAliasResolvers,
            $this->container,
            new RequestExpressionMatcher()
        );
    }

    public function testGetEntityAliasResolverForUnsupportedRequestType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot find an entity alias resolver for the request "rest,another".');

        $requestType = new RequestType(['rest', 'another']);
        $registry = $this->getRegistry([]);
        $registry->getEntityAliasResolver($requestType);
    }

    public function testGetEntityAliasResolverShouldReturnDefaultResolverForNotFirstAndSecondRequestType(): void
    {
        $registry = $this->getRegistry(
            [
                ['default_entity_alias_resolver', '!first&!second'],
                ['first_entity_alias_resolver', 'first'],
                ['second_entity_alias_resolver', 'second']
            ]
        );

        $this->container->expects(self::once())
            ->method('get')
            ->with('default_entity_alias_resolver')
            ->willReturn($this->defaultResolver);

        $requestType = new RequestType(['rest']);
        self::assertSame($this->defaultResolver, $registry->getEntityAliasResolver($requestType));
        // test internal cache
        self::assertSame($this->defaultResolver, $registry->getEntityAliasResolver($requestType));
    }

    public function testGetEntityAliasResolverShouldReturnFirstResolverForFirstRequestType(): void
    {
        $registry = $this->getRegistry(
            [
                ['default_entity_alias_resolver', '!first&!second'],
                ['first_entity_alias_resolver', 'first'],
                ['second_entity_alias_resolver', 'second']
            ]
        );

        $this->container->expects(self::once())
            ->method('get')
            ->with('first_entity_alias_resolver')
            ->willReturn($this->firstResolver);

        $requestType = new RequestType(['rest', 'first']);
        self::assertSame($this->firstResolver, $registry->getEntityAliasResolver($requestType));
        // test internal cache
        self::assertSame($this->firstResolver, $registry->getEntityAliasResolver($requestType));
    }

    public function testGetEntityAliasResolverShouldReturnSecondResolverForSecondRequestType(): void
    {
        $registry = $this->getRegistry(
            [
                ['default_entity_alias_resolver', '!first&!second'],
                ['first_entity_alias_resolver', 'first'],
                ['second_entity_alias_resolver', 'second']
            ]
        );

        $this->container->expects(self::once())
            ->method('get')
            ->with('second_entity_alias_resolver')
            ->willReturn($this->secondResolver);

        $requestType = new RequestType(['rest', 'second']);
        self::assertSame($this->secondResolver, $registry->getEntityAliasResolver($requestType));
        // test internal cache
        self::assertSame($this->secondResolver, $registry->getEntityAliasResolver($requestType));
    }

    public function testGetEntityAliasResolverShouldReturnDefaultResolverIfSpecificResolverNotFound(): void
    {
        $registry = $this->getRegistry(
            [
                ['first_entity_alias_resolver', 'first'],
                ['default_entity_alias_resolver', '']
            ]
        );

        $this->container->expects(self::once())
            ->method('get')
            ->with('default_entity_alias_resolver')
            ->willReturn($this->defaultResolver);

        $requestType = new RequestType(['rest', 'another']);
        self::assertSame($this->defaultResolver, $registry->getEntityAliasResolver($requestType));
        // test internal cache
        self::assertSame($this->defaultResolver, $registry->getEntityAliasResolver($requestType));
    }

    public function testWarmUpCache(): void
    {
        $registry = $this->getRegistry(
            [
                ['default_entity_alias_resolver', '!first&!second'],
                ['first_entity_alias_resolver', 'first'],
                ['second_entity_alias_resolver', 'second']
            ]
        );

        $this->container->expects(self::exactly(3))
            ->method('get')
            ->willReturnMap([
                ['default_entity_alias_resolver', $this->defaultResolver],
                ['first_entity_alias_resolver', $this->firstResolver],
                ['second_entity_alias_resolver', $this->secondResolver]
            ]);

        $this->defaultResolver->expects(self::once())
            ->method('warmUpCache');
        $this->firstResolver->expects(self::once())
            ->method('warmUpCache');
        $this->secondResolver->expects(self::once())
            ->method('warmUpCache');

        $registry->warmUpCache();
    }

    public function testClearCache(): void
    {
        $registry = $this->getRegistry(
            [
                ['default_entity_alias_resolver', '!first&!second'],
                ['first_entity_alias_resolver', 'first'],
                ['second_entity_alias_resolver', 'second']
            ]
        );

        $this->container->expects(self::exactly(3))
            ->method('get')
            ->willReturnMap([
                ['default_entity_alias_resolver', $this->defaultResolver],
                ['first_entity_alias_resolver', $this->firstResolver],
                ['second_entity_alias_resolver', $this->secondResolver]
            ]);

        $this->defaultResolver->expects(self::once())
            ->method('clearCache');
        $this->firstResolver->expects(self::once())
            ->method('clearCache');
        $this->secondResolver->expects(self::once())
            ->method('clearCache');

        $registry->clearCache();
    }
}
