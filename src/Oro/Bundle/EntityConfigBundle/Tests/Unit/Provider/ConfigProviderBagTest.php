<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Provider;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderBag;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigBag;
use Oro\Component\DependencyInjection\ServiceLink;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigProviderBagTest extends TestCase
{
    private PropertyConfigBag&MockObject $configBag;
    private ConfigManager&MockObject $configManager;
    private ConfigProviderBag $configProviderBag;

    #[\Override]
    protected function setUp(): void
    {
        $this->configBag = $this->createMock(PropertyConfigBag::class);
        $this->configManager = $this->createMock(ConfigManager::class);

        $configManagerLink = $this->createMock(ServiceLink::class);
        $configManagerLink->expects(self::any())
            ->method('getService')
            ->willReturn($this->configManager);

        $this->configProviderBag = new ConfigProviderBag(['scope1'], $configManagerLink, $this->configBag);
    }

    public function testGetProviderForExistingScope(): void
    {
        $provider = $this->configProviderBag->getProvider('scope1');
        self::assertInstanceOf(ConfigProvider::class, $provider);

        // test that cached provider is returned
        self::assertSame($provider, $this->configProviderBag->getProvider('scope1'));
    }

    public function testGetProviderForNotExistingScope(): void
    {
        self::assertNull($this->configProviderBag->getProvider('scope2'));
    }

    public function testGetProviders(): void
    {
        $providers = $this->configProviderBag->getProviders();
        self::assertCount(1, $providers);
        self::assertArrayHasKey('scope1', $providers);
        self::assertInstanceOf(ConfigProvider::class, $providers['scope1']);

        // test that cached providers are returned
        $cachedProviders = $this->configProviderBag->getProviders();
        self::assertCount(1, $cachedProviders);
        self::assertArrayHasKey('scope1', $cachedProviders);
        self::assertSame($providers['scope1'], $cachedProviders['scope1']);
    }
}
