<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Form\Util;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Form\Util\AssociationTypeHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AssociationTypeHelperTest extends TestCase
{
    private ConfigManager&MockObject $configManager;
    private AssociationTypeHelper $typeHelper;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);

        $this->typeHelper = new AssociationTypeHelper($this->configManager);
    }

    public function testIsDictionaryNoConfig(): void
    {
        $className = 'Test\Entity';

        $configProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('grouping')
            ->willReturn($configProvider);
        $configProvider->expects($this->once())
            ->method('hasConfig')
            ->with($className)
            ->willReturn(false);
        $configProvider->expects($this->never())
            ->method('getConfig');

        $this->assertFalse(
            $this->typeHelper->isDictionary($className)
        );
    }

    /**
     * @dataProvider isDictionaryProvider
     */
    public function testIsDictionary(?array $groups, bool $expected): void
    {
        $className = 'Test\Entity';

        $config = $this->createMock(Config::class);
        $config->expects($this->once())
            ->method('get')
            ->with('groups')
            ->willReturn($groups);

        $configProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('grouping')
            ->willReturn($configProvider);
        $configProvider->expects($this->once())
            ->method('hasConfig')
            ->with($className)
            ->willReturn(true);
        $configProvider->expects($this->once())
            ->method('getConfig')
            ->with($className)
            ->willReturn($config);

        $this->assertEquals(
            $expected,
            $this->typeHelper->isDictionary($className)
        );
    }

    public function isDictionaryProvider(): array
    {
        return [
            [null, false],
            [[], false],
            [['some_group'], false],
            [['dictionary'], true],
            [['some_group', 'dictionary'], true],
        ];
    }

    public function testGetOwningSideEntities(): void
    {
        $config1 = new Config(new EntityConfigId('grouping', 'Test\Entity1'));
        $config1->set('groups', ['some_group', 'another_group']);
        $config2 = new Config(new EntityConfigId('grouping', 'Test\Entity2'));
        $config2->set('groups', ['another_group']);
        $config3 = new Config(new EntityConfigId('grouping', 'Test\Entity3'));

        $configs = [$config1, $config2, $config3];

        $configProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->exactly(2))
            ->method('getProvider')
            ->with('grouping')
            ->willReturn($configProvider);
        $configProvider->expects($this->exactly(2))
            ->method('getConfigs')
            ->willReturn($configs);

        $this->assertEquals(
            ['Test\Entity1'],
            $this->typeHelper->getOwningSideEntities('some_group')
        );
        // one more call to check caching
        $this->assertEquals(
            ['Test\Entity1'],
            $this->typeHelper->getOwningSideEntities('some_group')
        );
        // call with another group to check a caching has no collisions
        $this->assertEquals(
            ['Test\Entity1', 'Test\Entity2'],
            $this->typeHelper->getOwningSideEntities('another_group')
        );
    }
}
