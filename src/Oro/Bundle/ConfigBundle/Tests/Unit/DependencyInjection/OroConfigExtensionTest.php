<?php

namespace Oro\Bundle\ConfigBundle\Tests\Unit\DependencyInjection;

use Oro\Bundle\ConfigBundle\DependencyInjection\OroConfigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OroConfigExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new OroConfigExtension();
        $extension->load([], $container);

        self::assertNotEmpty($container->getDefinitions());
    }
}
