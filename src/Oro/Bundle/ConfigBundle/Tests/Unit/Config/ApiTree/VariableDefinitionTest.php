<?php

namespace Oro\Bundle\ConfigBundle\Tests\Unit\Config\ApiTree;

use Oro\Bundle\ConfigBundle\Config\ApiTree\VariableDefinition;
use PHPUnit\Framework\TestCase;

class VariableDefinitionTest extends TestCase
{
    public function testVariableDefinition(): void
    {
        $key = 'test_key';
        $type = 'string';
        $variable = new VariableDefinition($key, $type);
        self::assertEquals($key, $variable->getKey());
        self::assertEquals($type, $variable->getType());
        self::assertEquals(
            [
                'key'  => $key,
                'type' => $type
            ],
            $variable->toArray()
        );
    }
}
