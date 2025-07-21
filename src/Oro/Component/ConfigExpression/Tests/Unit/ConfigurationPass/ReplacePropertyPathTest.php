<?php

namespace Oro\Component\ConfigExpression\Tests\Unit\ConfigurationPass;

use Oro\Component\ConfigExpression\ConfigurationPass\ReplacePropertyPath;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyPath;

class ReplacePropertyPathTest extends TestCase
{
    /**
     * @dataProvider passDataProvider
     */
    public function testPassConfiguration(array $sourceData, array $expectedData, ?string $prefix = null): void
    {
        $parameterPass = new ReplacePropertyPath($prefix);
        $actualData = $parameterPass->passConfiguration($sourceData);

        $this->assertEquals($expectedData, $actualData);
    }

    public function passDataProvider(): array
    {
        return [
            'empty data' => [
                'sourceData'   => [],
                'expectedData' => []
            ],
            'data with paths' => [
                'sourceData'   => [
                    'a' => '$path.component',
                    'b' => ['c' => '$another.path.component'],
                    'c' => '\$path.component'
                ],
                'expectedData' => [
                    'a' => new PropertyPath('path.component'),
                    'b' => ['c' => new PropertyPath('another.path.component')],
                    'c' => '$path.component'
                ]
            ],
            'data with prefix' => [
                'sourceData' => [
                    'a' => '$path.component',
                    'b' => ['c' => '$another.path.component'],
                    'c' => '\$path.component'
                ],
                'expectedData' => [
                    'a' => new PropertyPath('prefix.path.component'),
                    'b' => ['c' => new PropertyPath('prefix.another.path.component')],
                    'c' => '$path.component'
                ],
                'prefix' => 'prefix'
            ],
            'data with root ignore prefix' => [
                'sourceData' => [
                    'a' => '$.path.component',
                    'b' => [
                        'c' => '$.another.path.component'
                    ]
                ],
                'expectedData' => [
                    'a' => new PropertyPath('path.component'),
                    'b' => ['c' => new PropertyPath('another.path.component')]
                ],
                'prefix' => 'prefix'
            ],
        ];
    }

    public function testLocalCache(): void
    {
        $parameterPass = new ReplacePropertyPath();
        $actualData = $parameterPass->passConfiguration(['a' => '$path']);

        $this->assertEquals(
            ['a' => new PropertyPath('path')],
            $actualData
        );

        $propertyPath = $actualData['a'];

        $actualData = $parameterPass->passConfiguration(['b' => '$path']);
        $this->assertEquals(
            ['b' => new PropertyPath('path')],
            $actualData
        );

        $this->assertSame($propertyPath, $actualData['b']);
    }
}
