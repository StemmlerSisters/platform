<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Helper;

use Oro\Bundle\EntityBundle\Helper\UnidirectionalFieldHelper;
use Oro\Bundle\EntityBundle\Tests\Unit\Fixtures\Stub\TestEntity1;
use PHPUnit\Framework\TestCase;

class UnidirectionalFieldHelperTest extends TestCase
{
    /**
     * @dataProvider isFieldUnidirectionalDataProvider
     */
    public function testIsFieldUnidirectional(string $fieldName, bool $expected): void
    {
        $this->assertEquals($expected, UnidirectionalFieldHelper::isFieldUnidirectional($fieldName));
    }

    public function isFieldUnidirectionalDataProvider(): array
    {
        return [
            'unidirectional' => [
                'fieldName' => sprintf('unidirectional%sfield', UnidirectionalFieldHelper::DELIMITER),
                'expected' => true,
            ],
            'not unidirectional' => [
                'fieldName' => 'not_unidirectional_field',
                'expected' => false,
            ],
            'null' => [
                'fieldName' => '',
                'expected' => false,
            ],
            '2 delimiters' => [
                'fieldName' => implode(UnidirectionalFieldHelper::DELIMITER, ['some', 'test', 'field']),
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider getRealFieldNameDataProvider
     */
    public function testGetRealFieldName(string $fieldName, string $expected): void
    {
        $this->assertEquals($expected, UnidirectionalFieldHelper::getRealFieldName($fieldName));
    }

    public function getRealFieldNameDataProvider(): array
    {
        return [
            'unidirectional' => [
                'fieldName' => sprintf('unidirectional%sfield', UnidirectionalFieldHelper::DELIMITER),
                'expected' => 'field',
            ],
            'not unidirectional' => [
                'fieldName' => 'not_unidirectional_field',
                'expected' => 'not_unidirectional_field',
            ],
            'null' => [
                'fieldName' => '',
                'expected' => '',
            ],
            '2 delimiters' => [
                'fieldName' => implode(UnidirectionalFieldHelper::DELIMITER, ['some', 'test', 'field']),
                'expected' => implode(UnidirectionalFieldHelper::DELIMITER, ['some', 'test', 'field']),
            ],
        ];
    }

    /**
     * @dataProvider getRealFieldClassDataProvider
     */
    public function testGetRealFieldClass(string $fieldName, string $expected): void
    {
        $this->assertEquals($expected, UnidirectionalFieldHelper::getRealFieldClass($fieldName));
    }

    public function getRealFieldClassDataProvider(): array
    {
        return [
            'unidirectional' => [
                'fieldName' => sprintf('unidirectional%sfield', UnidirectionalFieldHelper::DELIMITER),
                'expected' => 'unidirectional',
            ],
            'not unidirectional' => [
                'fieldName' => 'not_unidirectional_field',
                'expected' => '',
            ],
            'null' => [
                'fieldName' => '',
                'expected' => '',
            ],
            '2 delimiters' => [
                'fieldName' => implode(UnidirectionalFieldHelper::DELIMITER, ['some', 'test', 'field']),
                'expected' => '',
            ],
        ];
    }

    public function testCreateUnidirectionalField(): void
    {
        $fieldName = 'fieldName';
        $expected = 'Oro\Bundle\EntityBundle\Tests\Unit\Fixtures\Stub\TestEntity1::fieldName';

        $this->assertEquals(
            $expected,
            UnidirectionalFieldHelper::createUnidirectionalField(TestEntity1::class, $fieldName)
        );
    }
}
