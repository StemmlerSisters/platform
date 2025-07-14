<?php

namespace Oro\Component\Layout\Tests\Unit\Extension\Theme\Model;

use Oro\Component\Layout\Extension\Theme\Model\DependencyInitializer;
use Oro\Component\Layout\Tests\Unit\Extension\Theme\Stubs\LayoutUpdateWithDependency;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DependencyInitializerTest extends TestCase
{
    private Container $container;
    private DependencyInitializer $initializer;

    #[\Override]
    protected function setUp(): void
    {
        $this->container = new Container();

        $this->initializer = new DependencyInitializer($this->container);
    }

    public function testShouldNotFailWithNonObject(): void
    {
        $this->initializer->initialize(null);
    }

    public function testNoKnownDependenciesShouldNotDoAnything(): void
    {
        $object = $this->createMock(LayoutUpdateWithDependency::class);
        $object->expects($this->never())
            ->method('setContainer');

        $this->initializer->initialize($object);
    }

    public function testShouldInitializeDependencies(): void
    {
        $dependency = $this->createMock(ContainerInterface::class);

        $object = $this->createMock(LayoutUpdateWithDependency::class);
        $object->expects($this->once())
            ->method('setContainer')
            ->with($this->identicalTo($dependency));

        $this->container->set('dependency_service_id', $dependency);

        $this->initializer->addKnownDependency(
            ContainerAwareInterface::class,
            'setContainer',
            'dependency_service_id'
        );

        $this->initializer->initialize($object);
    }
}
