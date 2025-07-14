<?php

namespace Oro\Component\ConfigExpression\Tests\Unit\Condition;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Component\ConfigExpression\Condition;
use Oro\Component\ConfigExpression\Condition\Type;
use Oro\Component\ConfigExpression\ContextAccessor;
use Oro\Component\ConfigExpression\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyPath;

class TypeTest extends TestCase
{
    private Condition\Type $condition;

    #[\Override]
    protected function setUp(): void
    {
        $this->condition = new Type();
        $this->condition->setContextAccessor(new ContextAccessor());
    }

    public function testInitializeWithException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "left" must be property path');

        $this->condition->initialize([1, 2]);
    }

    /**
     * @dataProvider evaluateDataProvider
     */
    public function testEvaluate(array $options, array $context, bool $expectedResult): void
    {
        $this->assertSame($this->condition, $this->condition->initialize($options));
        $this->assertEquals($expectedResult, $this->condition->evaluate($context));
    }

    public function evaluateDataProvider(): array
    {
        return [
            'integer' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'integer'],
                'context'        => ['prop1' => 1],
                'expectedResult' => true
            ],
            'string' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'string'],
                'context'        => ['prop1' => 'string'],
                'expectedResult' => true
            ],
            'boolean' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'boolean'],
                'context'        => ['prop1' => false],
                'expectedResult' => true
            ],
            'array' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'array'],
                'context'        => ['prop1' => [2]],
                'expectedResult' => true
            ],
            'double' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'double'],
                'context'        => ['prop1' => 3.4],
                'expectedResult' => true
            ],
            'object' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'object'],
                'context'        => ['prop1' => new \stdClass()],
                'expectedResult' => true
            ],
            '\stdClass' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => '\stdClass'],
                'context'        => ['prop1' => new \stdClass()],
                'expectedResult' => true
            ],

            'no integer'  => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'integer'],
                'context'        => ['prop1' => 'string'],
                'expectedResult' => false
            ],
            'no string' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'string'],
                'context'        => ['prop1' => 5],
                'expectedResult' => false
            ],
            'no boolean' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'boolean'],
                'context'        => ['prop1' => 6],
                'expectedResult' => false
            ],
            'no array' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'array'],
                'context'        => ['prop1' => 7],
                'expectedResult' => false
            ],
            'no double' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'double'],
                'context'        => ['prop1' => 8],
                'expectedResult' => false
            ],
            'no object' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => 'object'],
                'context'        => ['prop1' => 9],
                'expectedResult' => false
            ],
            'no \stdClass' => [
                'options'        => ['left' => new PropertyPath('prop1'), 'right' => '\stdClass'],
                'context'        => ['prop1' => 10],
                'expectedResult' => false
            ],
        ];
    }

    /**
     * @dataProvider errorMessagesProvider
     */
    public function testErrorMessages(array $inputData, array $expectedData): void
    {
        $options = [new PropertyPath('prop'), $inputData['type']];
        $context = ['prop' => $inputData['value']];

        $this->condition->initialize($options);

        $this->condition->setMessage($inputData['message']);

        $errors = new ArrayCollection();

        $this->assertFalse($this->condition->evaluate($context, $errors));

        $this->assertCount(1, $errors);
        $this->assertEquals($expectedData, $errors->get(0));
    }

    public function errorMessagesProvider(): array
    {
        return [
            'integer value (scalar)' => [
                'input' => [
                    'type' => 'string',
                    'value' => 1,
                    'message' => 'Error message1',
                ],
                'expected' => [
                    'message' => 'Error message1',
                    'parameters' => [
                        '{{ value }}' => '(integer)1',
                        '{{ type }}' => 'string',
                    ]
                ],
            ],
            'string value (scalar)' => [
                'input' => [
                    'type' => 'integer',
                    'value' => 'str',
                    'message' => 'Error message2',
                ],
                'expected' => [
                    'message' => 'Error message2',
                    'parameters' => [
                        '{{ value }}' => '(string)str',
                        '{{ type }}' => 'integer',
                    ]
                ],
            ],
            'array value (!scalar)' => [
                'input' => [
                    'type' => 'integer',
                    'value' => ['str'],
                    'message' => 'Error message3',
                ],
                'expected' => [
                    'message' => 'Error message3',
                    'parameters' => [
                        '{{ value }}' => 'array',
                        '{{ type }}' => 'integer',
                    ]
                ],
            ],
            'object value (object)' => [
                'input' => [
                    'type' => 'integer',
                    'value' => new \stdClass(),
                    'message' => 'Error message4',
                ],
                'expected' => [
                    'message' => 'Error message4',
                    'parameters' => [
                        '{{ value }}' => '(object)stdClass',
                        '{{ type }}' => 'integer',
                    ]
                ],
            ],
        ];
    }
}
