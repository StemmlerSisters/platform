<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Validator;

use Oro\Bundle\FormBundle\Validator\ConstraintFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\When;

class ConstraintFactoryTest extends TestCase
{
    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(object $expected, string $name, ?array $options): void
    {
        $factory = new ConstraintFactory();
        $this->assertEquals($expected, $factory->create($name, $options));
    }

    public function createDataProvider(): array
    {
        return [
            'short name'        => [
                'expectedClass' => new NotBlank(),
                'name'          => 'NotBlank',
                'options'       => null,
            ],
            'custom class name' => [
                'expectedClass' => new Length(['min' => 2, 'max' => 255]),
                'name'          => Length::class,
                'options'       => ['min' => 2, 'max' => 255],
            ],
        ];
    }

    public function testCreateInvalidConstraint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory = new ConstraintFactory();
        $factory->create('test');
    }

    /**
     * @dataProvider constraintsProvider
     */
    public function testParse(array $constraints, array $expected): void
    {
        $factory = new ConstraintFactory();
        $this->assertEquals($expected, $factory->parse($constraints));
    }

    public function constraintsProvider(): array
    {
        return [
            'empty'              => [
                'constraints' => [],
                'expected'    => []
            ],
            'constraint object'  => [
                'constraints' => [
                    new NotBlank()
                ],
                'expected'    => [
                    new NotBlank()
                ]
            ],
            'by name'            => [
                'constraints' => [
                    [
                        'NotBlank' => null
                    ]
                ],
                'expected'    => [
                    new NotBlank()
                ]
            ],
            'by full class name' => [
                'constraints' => [
                    [
                        Length::class => ['min' => 1, 'max' => 2]
                    ]
                ],
                'expected'    => [
                    new Length(['min' => 1, 'max' => 2])
                ]
            ],
            'with nested constraints' => [
                'constraints' => [
                    [
                        'When' => [
                            'expression' => 'true',
                            'constraints' => [
                                ['NotBlank' => null],
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    new When(['expression' => 'true', 'constraints' => [new NotBlank()]]),
                ],
            ],
        ];
    }

    /**
     * @dataProvider invalidConstraintsProvider
     */
    public function testParseWithInvalidArgument(array $constraints): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory = new ConstraintFactory();
        $factory->parse($constraints);
    }

    public function invalidConstraintsProvider(): array
    {
        return [
            [
                'constraints' => [
                    'test'
                ]
            ],
            [
                'constraints' => [
                    ['test' => null]
                ]
            ],
            [
                'constraints' => [
                    ['Test\UndefinedClass' => null]
                ]
            ],
            [
                'constraints' => [
                    new \stdClass()
                ]
            ],
        ];
    }
}
