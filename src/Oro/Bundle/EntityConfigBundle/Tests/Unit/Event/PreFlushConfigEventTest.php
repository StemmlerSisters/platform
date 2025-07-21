<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Event;

use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Event\PreFlushConfigEvent;
use PHPUnit\Framework\TestCase;

class PreFlushConfigEventTest extends TestCase
{
    public function testEvent(): void
    {
        $config1 = $this->createMock(ConfigInterface::class);
        $config2 = $this->createMock(ConfigInterface::class);
        $configs = ['scope1' => $config1, 'scope2' => $config2];

        $configManager = $this->createMock(ConfigManager::class);

        $event = new PreFlushConfigEvent($configs, $configManager);

        $this->assertSame($configManager, $event->getConfigManager());
        $this->assertEquals($configs, $event->getConfigs());
        $this->assertSame($config1, $event->getConfig('scope1'));
        $this->assertSame($config2, $event->getConfig('scope2'));
        $this->assertNull($event->getConfig('another_scope'));
    }

    public function testGetClass(): void
    {
        $config1 = $this->createMock(ConfigInterface::class);
        $config2 = $this->createMock(ConfigInterface::class);
        $configs = ['scope1' => $config1, 'scope2' => $config2];

        $configManager = $this->createMock(ConfigManager::class);

        $className = 'Test\Entity';

        $event = new PreFlushConfigEvent($configs, $configManager);

        $config1->expects($this->once())
            ->method('getId')
            ->willReturn(new EntityConfigId('scope1', $className));
        $config2->expects($this->never())
            ->method('getId');

        $this->assertEquals($className, $event->getClassName());
        // test that a local cache is used
        $this->assertEquals($className, $event->getClassName());
    }

    /**
     * @dataProvider isFieldConfigDataProvider
     */
    public function testIsFieldConfig(EntityConfigId|FieldConfigId $configId, bool $expectedResult): void
    {
        $config1 = $this->createMock(ConfigInterface::class);
        $config2 = $this->createMock(ConfigInterface::class);
        $configs = ['scope1' => $config1, 'scope2' => $config2];

        $configManager = $this->createMock(ConfigManager::class);

        $event = new PreFlushConfigEvent($configs, $configManager);

        $config1->expects($this->once())
            ->method('getId')
            ->willReturn($configId);
        $config2->expects($this->never())
            ->method('getId');

        $this->assertEquals($expectedResult, $event->isFieldConfig());
        // test that a local cache is used
        $this->assertEquals($expectedResult, $event->isFieldConfig());
    }

    public function isFieldConfigDataProvider(): array
    {
        return [
            [new EntityConfigId('scope1', 'Test\Entity'), false],
            [new FieldConfigId('scope1', 'Test\Entity', 'testField'), true]
        ];
    }

    /**
     * @dataProvider isEntityConfigDataProvider
     */
    public function testIsEntityConfig(EntityConfigId|FieldConfigId $configId, bool $expectedResult): void
    {
        $config1 = $this->createMock(ConfigInterface::class);
        $config2 = $this->createMock(ConfigInterface::class);
        $configs = ['scope1' => $config1, 'scope2' => $config2];

        $configManager = $this->createMock(ConfigManager::class);

        $event = new PreFlushConfigEvent($configs, $configManager);

        $config1->expects($this->once())
            ->method('getId')
            ->willReturn($configId);
        $config2->expects($this->never())
            ->method('getId');

        $this->assertEquals($expectedResult, $event->isEntityConfig());
        // test that a local cache is used
        $this->assertEquals($expectedResult, $event->isEntityConfig());
    }

    public function isEntityConfigDataProvider(): array
    {
        return [
            [new EntityConfigId('scope1', 'Test\Entity'), true],
            [new FieldConfigId('scope1', 'Test\Entity', 'testField'), false]
        ];
    }
}
