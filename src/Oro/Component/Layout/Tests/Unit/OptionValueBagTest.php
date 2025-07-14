<?php

namespace Oro\Component\Layout\Tests\Unit;

use Oro\Component\Layout\OptionValueBag;
use PHPUnit\Framework\TestCase;

class OptionValueBagTest extends TestCase
{
    /**
     * @dataProvider buildValueProvider
     *
     * @param array $actions
     * @param mixed $expectedValue
     */
    public function testBuildValue(array $actions, $expectedValue): void
    {
        $optionValueBag = $this->createOptionValueBag($actions);
        $this->assertEquals($expectedValue, $optionValueBag->buildValue());
    }

    public function buildValueProvider(): array
    {
        return [
            'empty value' => [
                'actions' => [],
                'value' => '',
            ],
            'string value with simple manipulations' => [
                'actions' => [
                        ['method' => 'add', 'arguments' => ['first']],
                        ['method' => 'add', 'arguments' => ['second']],
                ],
                'expectedValue' => 'first second',
            ],
            'string value with complex manipulations' => [
                'actions' => [
                    ['method' => 'add', 'arguments' => ['first']],
                    ['method' => 'add', 'arguments' => ['second']],
                    ['method' => 'add', 'arguments' => ['third']],
                    ['method' => 'replace', 'arguments' => ['second', 'fourth']],
                    ['method' => 'remove', 'arguments' => ['first']],
                ],
                'expectedValue' => 'fourth third',
            ],
            'array value with simple manipulations' => [
                'actions' => [
                    ['method' => 'add', 'arguments' => [['first', 'second']]],
                    ['method' => 'add', 'arguments' => [['third']]],
                ],
                'expectedValue' => ['first', 'second', 'third'],
            ],
            'array value with complex manipulations' => [
                'actions' => [
                    ['method' => 'add', 'arguments' => [['first', 'second']]],
                    ['method' => 'add', 'arguments' => [['third']]],
                    ['method' => 'replace', 'arguments' => [['second'], ['fourth']]],
                    ['method' => 'remove', 'arguments' => [['first']]],
                ],
                'expectedValue' => ['fourth', 'third'],
            ]
        ];
    }

    private function createOptionValueBag(array $actions): OptionValueBag
    {
        $bag = new OptionValueBag();
        foreach ($actions as $action) {
            call_user_func_array([$bag, $action['method']], $action['arguments']);
        }
        return $bag;
    }
}
