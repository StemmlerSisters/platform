<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Config;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testCloneConfig(): void
    {
        $config = new Config(new EntityConfigId('testScope', 'testClass'));

        $values = ['firstKey' => 'firstValue', 'secondKey' => new \stdClass()];
        $config->setValues($values);

        $clone = clone $config;

        $this->assertEquals($config, $clone);
        $this->assertNotSame($config, $clone);
    }

    public function testValueConfig(): void
    {
        $config = new Config(new EntityConfigId('testScope', 'testClass'));

        $values = [
            'firstKey' => 'firstValue',
            'secondKey' => 'secondValue',
            'thirdKey' => 3,
            'fourthKey' => new \stdClass(),
            'falseKey' => false,
            'nullKey' => null,
        ];
        $config->setValues($values);

        $this->assertEquals($values, $config->all());
        $this->assertEquals(
            ['firstKey' => 'firstValue'],
            $config->all(function ($value) {
                return 'firstValue' === $value;
            })
        );

        $this->assertEquals('firstValue', $config->get('firstKey'));
        $this->assertEquals('secondValue', $config->get('secondKey'));

        $this->assertTrue($config->is('secondKey'));

        $this->assertTrue($config->in('thirdKey', ['3']));
        $this->assertFalse($config->in('thirdKey', ['3'], true));
        $this->assertTrue($config->in('thirdKey', [3]));
        $this->assertTrue($config->in('thirdKey', [3], true));
        $this->assertFalse($config->in('thirdKey', [100]));

        $this->assertTrue($config->has('secondKey'));
        $this->assertFalse($config->has('nonExistKey'));
        $this->assertTrue($config->has('falseKey'));
        $this->assertTrue($config->has('nullKey'));

        $this->assertNull($config->get('nonExistKey'));
        $this->assertFalse($config->get('falseKey'));
        $this->assertNull($config->get('nullKey'));

        $this->assertEquals($config, unserialize(serialize($config)));

        $config->set('secondKey', 'secondValue2');
        $this->assertEquals('secondValue2', $config->get('secondKey'));

        $this->assertEquals(112233, $config->get('nonExistKey', false, 112233));
        $this->assertEquals('default', $config->get('nonExistKey', false, 'default'));
        $this->assertEquals([], $config->get('nonExistKey', false, []));

        $this->expectException(RuntimeException::class);
        $config->get('nonExistKey', true);
    }

    public function testSetState(): void
    {
        $configId = new EntityConfigId('testScope', 'Test\Class');
        $configValues = ['test' => 'testVal'];
        $config = Config::__set_state(
            [
                'id' => $configId,
                'values' => $configValues,
            ]
        );
        $this->assertEquals($configId, $config->getId());
        $this->assertEquals($configValues['test'], $config->get('test'));
    }
}
