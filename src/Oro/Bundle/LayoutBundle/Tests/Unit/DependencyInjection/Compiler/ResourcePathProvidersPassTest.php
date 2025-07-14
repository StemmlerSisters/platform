<?php

namespace Oro\Bundle\LayoutBundle\Tests\Unit\DependencyInjection\Compiler;

use Oro\Bundle\LayoutBundle\DependencyInjection\Compiler\ResourcePathProvidersPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ResourcePathProvidersPassTest extends TestCase
{
    private ResourcePathProvidersPass $compiler;

    #[\Override]
    protected function setUp(): void
    {
        $this->compiler = new ResourcePathProvidersPass();
    }

    public function testNoTaggedServicesFound(): void
    {
        $container = new ContainerBuilder();
        $pathProviderDef = $container->register('oro_layout.loader.path_provider');

        $this->compiler->process($container);

        $this->assertEmpty($pathProviderDef->getMethodCalls());
    }

    public function testFoundProviders(): void
    {
        $container = new ContainerBuilder();
        $pathProviderDef = $container->register('oro_layout.loader.path_provider');

        $container->register('provider1')
            ->addTag('layout.resource.path_provider', ['priority' => 100]);
        $container->register('provider2')
            ->addTag('layout.resource.path_provider');

        $this->compiler->process($container);

        $this->assertEquals(
            [
                ['addProvider', [new Reference('provider1'), 100]],
                ['addProvider', [new Reference('provider2'), 0]]
            ],
            $pathProviderDef->getMethodCalls()
        );
    }
}
