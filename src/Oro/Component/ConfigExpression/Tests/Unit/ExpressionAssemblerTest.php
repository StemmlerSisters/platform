<?php

namespace Oro\Component\ConfigExpression\Tests\Unit;

use Oro\Component\ConfigExpression\ConfigurationPass\ConfigurationPassInterface;
use Oro\Component\ConfigExpression\ExpressionAssembler;
use Oro\Component\ConfigExpression\ExpressionFactoryInterface;
use Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ExpressionStub;
use PHPUnit\Framework\TestCase;

class ExpressionAssemblerTest extends TestCase
{
    /**
     * @dataProvider configurationDataProvider
     */
    public function testAssemble($configuration, $expected): void
    {
        $factory = $this->createMock(ExpressionFactoryInterface::class);
        $factory->expects($this->any())
            ->method('create')
            ->willReturnCallback(function ($type, $options) {
                $expr = new ExpressionStub($type);

                return $expr->initialize($options);
            });

        $configurationPass = $this->createMock(ConfigurationPassInterface::class);

        $configurationPass->expects($this->any())
            ->method('passConfiguration')
            ->willReturnCallback(function ($options) {
                return ['passed' => $options];
            });

        $assembler = new ExpressionAssembler($factory);
        $assembler->addConfigurationPass($configurationPass);

        $actual = $assembler->assemble($configuration);
        $this->assertEquals($expected, $actual);
    }

    public function configurationDataProvider(): array
    {
        return [
            [
                [
                    '@or' => [
                        'parameters' => [
                            [
                                '@and' => [
                                    ['@graterOrEquals' => ['parameters' => ['$contact.budget', 2000]]],
                                    ['@testWithoutParameters1' => null],
                                    ['@testWithoutParameters2' => ['parameters' => null]],
                                    ['@testWithParameter1' => 'test'],
                                    ['@testWithParameter2' => ['parameters' => 'test']],
                                    ['@testWithMessage1' => ['test', 'message' => 'TEST MSG']],
                                    ['@testWithMessage2' => ['parameters' => ['test', 'message' => 'TEST MSG']]],
                                    ['@inChoiceList' => ['type' => '$contact.type', ['a' => 1, 'b' => 2]]]
                                ]
                            ],
                            [
                                '@blank' => [
                                    ['@trim' => ['$lead.name']]
                                ]
                            ]
                        ],
                        'message'    => 'Or fail'
                    ]
                ],
                new ExpressionStub(
                    'or',
                    [
                        'passed' => [
                            new ExpressionStub(
                                'and',
                                [
                                    'passed' => [
                                        new ExpressionStub(
                                            'graterOrEquals',
                                            ['passed' => ['$contact.budget', 2000]]
                                        ),
                                        new ExpressionStub(
                                            'testWithoutParameters1',
                                            ['passed' => []]
                                        ),
                                        new ExpressionStub(
                                            'testWithoutParameters2',
                                            ['passed' => []]
                                        ),
                                        new ExpressionStub(
                                            'testWithParameter1',
                                            ['passed' => ['test']]
                                        ),
                                        new ExpressionStub(
                                            'testWithParameter2',
                                            ['passed' => ['test']]
                                        ),
                                        new ExpressionStub(
                                            'testWithMessage1',
                                            ['passed' => ['test']],
                                            'TEST MSG'
                                        ),
                                        new ExpressionStub(
                                            'testWithMessage2',
                                            ['passed' => ['test']],
                                            'TEST MSG'
                                        ),
                                        new ExpressionStub(
                                            'inChoiceList',
                                            ['passed' => ['type' => '$contact.type', ['a' => 1, 'b' => 2]]]
                                        ),
                                    ]
                                ]
                            ),
                            new ExpressionStub(
                                'blank',
                                [
                                    'passed' => [
                                        new ExpressionStub(
                                            'trim',
                                            ['passed' => ['$lead.name']]
                                        )
                                    ]
                                ]
                            )
                        ]
                    ],
                    'Or fail'
                )
            ],
            [
                [],
                null
            ]
        ];
    }
}
