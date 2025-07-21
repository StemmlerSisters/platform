<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Oro\Bundle\EntityBundle\DBAL\Types\ConfigObjectType;
use Oro\Component\Config\Common\ConfigObject;
use PHPUnit\Framework\TestCase;

class ConfigObjectTest extends TestCase
{
    private ConfigObjectType $type;

    #[\Override]
    protected function setUp(): void
    {
        $this->type = new ConfigObjectType();
    }

    /**
     * @dataProvider testConvertToPHPValueDataProvider
     */
    public function testConvertToPHPValue(?bool $inputData, ?bool $expectedResult, bool $exception = false): void
    {
        if ($exception) {
            $this->expectException(ConversionException::class);
            $this->expectExceptionMessage(
                'Could not convert database value "' . $inputData . '" to Doctrine Type config_object'
            );
        }

        $this->assertSame(
            $expectedResult,
            $this->type->convertToPHPValue($inputData, $this->createMock(AbstractPlatform::class))
        );
    }

    public function testGetConfigObjectFromConvertToPHPValueMethod(): void
    {
        $testArray = ['name' => 'test'];
        $result = $this->type->convertToPHPValue(
            json_encode($testArray, JSON_THROW_ON_ERROR),
            $this->createMock(AbstractPlatform::class)
        );
        $this->assertInstanceOf(ConfigObject::class, $result);
        $this->assertSame($testArray, $result->toArray());
    }

    /**
     * @dataProvider testConvertToDatabaseValueDataProvider
     */
    public function testConvertToDatabaseValue(mixed $inputData, ?string $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            $this->type->convertToDatabaseValue($inputData, $this->createMock(AbstractPlatform::class))
        );
    }

    public function testConvertToPHPValueDataProvider(): array
    {
        return [
            'null input' => [
                'input' => null,
                'expected' => null
            ],
            'incorrect input' => [
                'input' => false,
                'expected' => false,
                'exception' => true
            ]
        ];
    }

    public function testConvertToDatabaseValueDataProvider(): array
    {
        return [
            'null input' => [
                'input' => null,
                'expected' => null
            ],
            'object input' => [
                'input' => ConfigObject::create(['name' => 'test']),
                'expected' => json_encode(['name' => 'test'], JSON_THROW_ON_ERROR)
            ],
            'incorrect input' => [
                'input' => 'some incorrect value',
                'expected' => json_encode([], JSON_THROW_ON_ERROR)
            ]
        ];
    }
}
