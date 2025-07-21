<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Resolver;

use Oro\Bundle\ActionBundle\Resolver\DestinationPageResolver;
use Oro\Bundle\EntityConfigBundle\Helper\EntityConfigHelper;
use Oro\Bundle\TestFrameworkBundle\Entity\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class DestinationPageResolverTest extends TestCase
{
    private EntityConfigHelper&MockObject $entityConfigHelper;
    private RouterInterface&MockObject $router;
    private DestinationPageResolver $resolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->entityConfigHelper = $this->createMock(EntityConfigHelper::class);

        $this->resolver = new DestinationPageResolver($this->entityConfigHelper, $this->router);
    }

    public function testGetAvailableDestinationsForEntity(): void
    {
        $this->entityConfigHelper->expects($this->once())
            ->method('getRoutes')
            ->with('TestClass', ['view', 'name'])
            ->willReturn(['name' => 'index_route', 'view' => 'view_route', 'custom' => null]);

        $this->assertEquals([null, 'name', 'view'], $this->resolver->getAvailableDestinationsForEntity('TestClass'));
    }

    public function testGetAvailableDestinationsForEntityWithoutRoutes(): void
    {
        $this->entityConfigHelper->expects($this->once())
            ->method('getRoutes')
            ->with('TestClass', ['view', 'name'])
            ->willReturn(['custom' => 'custom_route']);

        $this->assertEquals([null, 'custom'], $this->resolver->getAvailableDestinationsForEntity('TestClass'));
    }

    public function testResolveDestinationUrl(): void
    {
        $entity = new Item();
        $entity->id = 10;

        $this->entityConfigHelper->expects($this->any())
            ->method('getRoutes')
            ->willReturn(['name' => 'index_route', 'view' => 'view_route']);

        $this->router->expects($this->any())
            ->method('generate')
            ->willReturnMap([
                ['index_route', [], RouterInterface::ABSOLUTE_PATH, 'example.com/index'],
                ['view_route', ['id' => 10], RouterInterface::ABSOLUTE_PATH, 'example.com/view'],
            ]);

        $this->assertEquals('example.com/index', $this->resolver->resolveDestinationUrl($entity, 'name'));
        $this->assertEquals('example.com/view', $this->resolver->resolveDestinationUrl($entity, 'view'));
        $this->assertEquals(null, $this->resolver->resolveDestinationUrl($entity, 'unknown_route'));
        $this->assertEquals(null, $this->resolver->resolveDestinationUrl(new Item(), 'view'));
    }
}
