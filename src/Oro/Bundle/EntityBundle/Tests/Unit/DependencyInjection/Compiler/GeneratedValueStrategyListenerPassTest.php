<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\DependencyInjection\Compiler;

use Oro\Bundle\EntityBundle\DependencyInjection\Compiler\GeneratedValueStrategyListenerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GeneratedValueStrategyListenerPassTest extends TestCase
{
    private GeneratedValueStrategyListenerPass $compiler;

    #[\Override]
    protected function setUp(): void
    {
        $this->compiler = new GeneratedValueStrategyListenerPass();
    }

    public function testProcess(): void
    {
        $container = new ContainerBuilder();
        $listenerDef = $container->register('oro_entity.listener.orm.generated_value_strategy_listener')
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
        $container->setParameter(
            'doctrine.connections',
            [
                'default' => 'doctrine.dbal.default_connection',
                'session' => 'doctrine.dbal.session_connection'
            ]
        );

        $this->compiler->process($container);

        self::assertEquals(
            [
                'doctrine.event_listener' => [
                    ['event' => 'loadClassMetadata', 'connection' => 'default'],
                    ['event' => 'loadClassMetadata', 'connection' => 'session']
                ]
            ],
            $listenerDef->getTags()
        );
    }

    public function testProcessWithoutDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(
            'doctrine.connections',
            [
                'default' => 'doctrine.dbal.default_connection',
                'session' => 'doctrine.dbal.session_connection'
            ]
        );

        $this->compiler->process($container);
    }

    public function testProcessWithoutDoctrineConnectionsParameter(): void
    {
        $container = new ContainerBuilder();
        $listenerDef = $container->register('oro_entity.listener.orm.generated_value_strategy_listener')
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

        $this->compiler->process($container);

        self::assertEquals(
            [
                'doctrine.event_listener' => [
                    ['event' => 'loadClassMetadata']
                ]
            ],
            $listenerDef->getTags()
        );
    }
}
