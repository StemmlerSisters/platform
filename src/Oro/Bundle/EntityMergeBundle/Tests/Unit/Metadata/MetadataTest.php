<?php

namespace Oro\Bundle\EntityMergeBundle\Tests\Unit\Metadata;

use Oro\Bundle\EntityMergeBundle\Exception\InvalidArgumentException;
use Oro\Bundle\EntityMergeBundle\Metadata\Metadata;
use PHPUnit\Framework\TestCase;

class MetadataTest extends TestCase
{
    public function testGetExistingStrict(): void
    {
        $metadata = new Metadata(['code' => 'value']);
        $this->assertEquals('value', $metadata->get('code', true));
    }

    public function testGetNonExistingStrict(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "code" not exists');

        $metadata = new Metadata();
        $this->assertEquals('value', $metadata->get('code', true));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testGet(array $options, string $code, mixed $expectedValue): void
    {
        $metadata = new Metadata($options);
        $this->assertEquals($expectedValue, $metadata->get($code));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testAllWithCallback(): void
    {
        $options = [
            'first'  => true,
            'second' => false,
        ];
        $metadata = new Metadata($options);

        $this->assertEquals(
            ['first' => true],
            $metadata->all(
                function ($value) {
                    return (bool)$value;
                }
            )
        );
    }

    /**
     * @dataProvider dataProvider
     */
    public function testMethods(
        array $options,
        string $code,
        mixed $expectedValue,
        string $hasMethod,
        string $isMethod,
        string $isNotExpected = 'assertFalse'
    ): void {
        $metadata = new Metadata($options);
        $metadata->set($code, $expectedValue);
        $this->$hasMethod($metadata->has($code));
        $this->$isMethod($metadata->is($code));
        $this->$isMethod($metadata->is($code, $expectedValue));
        $this->$isNotExpected($metadata->is($code, 'not_expected_value'));
        $this->assertEquals($expectedValue, $metadata->get($code));
        $this->assertEquals(array_merge($options, [$code => $expectedValue]), $metadata->all());
    }

    public function dataProvider(): array
    {
        return [
            'string'  => [
                'options'       => ['code-string' => 'value-string'],
                'code'          => 'code-string',
                'expectedValue' => 'value-string',
                'hasMethod'     => 'assertTrue',
                'isMethod'      => 'assertTrue',
            ],
            'integer' => [
                'options'       => ['code-integer' => 2],
                'code'          => 'code-integer',
                'expectedValue' => 2,
                'hasMethod'     => 'assertTrue',
                'isMethod'      => 'assertTrue',
            ],
            'bool'    => [
                'options'       => ['code-bool' => true],
                'code'          => 'code-bool',
                'expectedValue' => true,
                'hasMethod'     => 'assertTrue',
                'isMethod'      => 'assertTrue',
                'isNotExpected' => 'assertTrue',
            ],
            'object'  => [
                'options'       => ['code-object' => new \stdClass()],
                'code'          => 'code-object',
                'expectedValue' => new \stdClass(),
                'hasMethod'     => 'assertTrue',
                'isMethod'      => 'assertTrue',
            ],
            'null'    => [
                'options'       => ['code-null' => null],
                'code'          => 'code-null',
                'expectedValue' => null,
                'hasMethod'     => 'assertFalse',
                'isMethod'      => 'assertFalse',
            ],
            'empty'   => [
                'options'       => [],
                'code'          => 'any',
                'expectedValue' => null,
                'hasMethod'     => 'assertFalse',
                'isMethod'      => 'assertFalse',
            ],
            'another' => [
                'options'       => ['code-string' => 'value-string', 'code-bool' => true],
                'code'          => 'code-object',
                'expectedValue' => null,
                'hasMethod'     => 'assertFalse',
                'isMethod'      => 'assertFalse',
            ]
        ];
    }
}
