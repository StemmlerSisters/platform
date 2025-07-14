<?php

namespace Oro\Bundle\ActivityBundle\Tests\Unit\Routing;

use Oro\Bundle\ActivityBundle\Routing\ActivityAssociationRouteOptionsResolver;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Component\Routing\Resolver\EnhancedRouteCollection;
use Oro\Component\Routing\Resolver\RouteCollectionAccessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

class ActivityAssociationRouteOptionsResolverTest extends TestCase
{
    private ConfigProvider&MockObject $groupingConfigProvider;
    private EntityAliasResolver&MockObject $entityAliasResolver;
    private EnhancedRouteCollection $routeCollection;
    private RouteCollectionAccessor $routeCollectionAccessor;
    private ActivityAssociationRouteOptionsResolver $routeOptionsResolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->groupingConfigProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfigs'])
            ->getMock();
        $this->entityAliasResolver = $this->createMock(EntityAliasResolver::class);

        $this->routeOptionsResolver = new ActivityAssociationRouteOptionsResolver(
            $this->groupingConfigProvider,
            $this->entityAliasResolver
        );

        $this->routeCollection = new EnhancedRouteCollection();
        $this->routeCollectionAccessor = new RouteCollectionAccessor($this->routeCollection);
    }

    public function testResolveUnsupportedRoute(): void
    {
        $route = new Route('/route');

        $this->routeOptionsResolver->resolve($route, $this->routeCollectionAccessor);

        $this->assertEquals([], $route->getRequirements());
    }

    public function testResolveActivityRelationRouteWithoutEntityPlaceholder(): void
    {
        $route = new Route('/route', [], [], ['group' => ActivityAssociationRouteOptionsResolver::ROUTE_GROUP]);

        $this->routeOptionsResolver->resolve($route, $this->routeCollectionAccessor);

        $this->assertEquals([], $route->getRequirements());
    }

    public function testResolve(): void
    {
        $route = new Route(
            '/{activity}/route',
            [],
            [],
            ['group' => ActivityAssociationRouteOptionsResolver::ROUTE_GROUP]
        );

        $this->routeCollection->add('first_route', new Route('/first_route'));
        $this->routeCollection->add('override_before', new Route('/events/route'));
        $this->routeCollection->add('some_route', new Route('/some_route'));
        $this->routeCollection->add('tested_route', $route);
        $this->routeCollection->add('override_after', new Route('/emails/route'));
        $this->routeCollection->add('last_route', new Route('/last_route'));

        $config1 = new Config(new EntityConfigId('grouping', 'Test\Email'));
        $config1->set('groups', ['activity']);
        $config2 = new Config(new EntityConfigId('grouping', 'Test\Call'));
        $config2->set('groups', ['test', 'activity']);
        $config3 = new Config(new EntityConfigId('grouping', 'Test\Task'));
        $config3->set('groups', ['activity']);
        $config4 = new Config(new EntityConfigId('grouping', 'Test\Message'));
        $config5 = new Config(new EntityConfigId('grouping', 'Test\Event'));
        $config5->set('groups', ['activity']);

        $this->groupingConfigProvider->expects($this->once())
            ->method('getConfigs')
            ->with(null, false)
            ->willReturn([$config1, $config2, $config3, $config4, $config5]);

        $this->entityAliasResolver->expects($this->exactly(4))
            ->method('getPluralAlias')
            ->willReturnMap([
                ['Test\Email', 'emails'],
                ['Test\Call', 'calls'],
                ['Test\Task', 'tasks'],
                ['Test\Event', 'events']
            ]);

        $this->routeOptionsResolver->resolve($route, $this->routeCollectionAccessor);

        $this->assertEquals(
            ['activity' => '\w+'],
            $route->getRequirements()
        );

        $this->assertEquals(
            [
                'first_route',
                'some_route',
                'override_after', // emails
                'tested_route_auto_7', // calls
                'tested_route_auto_8', // tasks
                'override_before', // events
                'tested_route',
                'last_route'
            ],
            array_keys($this->routeCollection->all())
        );

        $this->assertEquals(
            '\w+',
            $this->routeCollection->get('tested_route')->getRequirement('activity')
        );
        $this->assertEquals(
            'calls',
            $this->routeCollection->get('tested_route_auto_7')->getDefault('activity')
        );
        $this->assertEquals(
            'tasks',
            $this->routeCollection->get('tested_route_auto_8')->getDefault('activity')
        );
    }
}
