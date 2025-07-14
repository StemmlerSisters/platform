<?php

namespace Oro\Bundle\RedisConfigBundle\Tests\Unit\DependencyInjection\Compiler;

use Oro\Bundle\RedisConfigBundle\DependencyInjection\Compiler\ConfigCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConfigCompilerPassTest extends TestCase
{
    public function testConfigSlugCacheWithoutEnabledRedisCache(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('oro_redirect.url_cache_type', 'storage');

        $compilerPass = new ConfigCompilerPass();
        $compilerPass->process($container);

        $this->assertEquals('storage', $container->getParameter('oro_redirect.url_cache_type'));
    }

    public function testConfigSlugCacheWithEnabledRedisCache(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('oro_redirect.url_cache_type', 'storage');
        $container->setParameter('redis_dsn_cache', 'redis://127.0.0.1:6379/0');

        $compilerPass = new ConfigCompilerPass();
        $compilerPass->process($container);

        $this->assertEquals('key_value', $container->getParameter('oro_redirect.url_cache_type'));
    }
}
