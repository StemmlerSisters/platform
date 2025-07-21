<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Tools;

use Oro\Bundle\EntityExtendBundle\Entity\EnumOption;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\Bundles\TestBundle1\TestBundle1;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestClass;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ExtendHelperTest extends TestCase
{
    /**
     * @dataProvider getReverseRelationTypeProvider
     */
    public function testGetReverseRelationType(string $type, string $expectedType): void
    {
        $this->assertEquals(
            $expectedType,
            ExtendHelper::getReverseRelationType($type)
        );
    }

    public static function getReverseRelationTypeProvider(): array
    {
        return [
            ['oneToMany', 'manyToOne'],
            ['manyToOne', 'oneToMany'],
            ['manyToMany', 'manyToMany'],
            ['other', 'other'],
        ];
    }

    /**
     * @dataProvider buildToManyRelationTargetFieldNameProvider
     */
    public function testBuildToManyRelationTargetFieldName(
        string $entityClassName,
        string $fieldName,
        string $expected
    ): void {
        $this->assertEquals(
            $expected,
            ExtendHelper::buildToManyRelationTargetFieldName($entityClassName, $fieldName)
        );
    }

    public static function buildToManyRelationTargetFieldNameProvider(): array
    {
        return [
            ['Oro\Bundle\TestBundle\Entity\Test', 'testField', 'test_testField'],
        ];
    }

    /**
     * @dataProvider buildAssociationNameProvider
     */
    public function testBuildAssociationName(
        string $targetEntityClassName,
        ?string $associationKind,
        string $expected
    ): void {
        $this->assertEquals(
            $expected,
            ExtendHelper::buildAssociationName($targetEntityClassName, $associationKind)
        );
    }

    public static function buildAssociationNameProvider(): array
    {
        return [
            ['Oro\Bundle\TestBundle\Entity\Test', null, 'test_d2f667e'],
            ['Oro\Bundle\TestBundle\Entity\Test', 'test', 'test_9a6fc24b'],
            ['Oro\Bundle\TestBundle\Entity\OtherTest', null, 'other_test_f1fe376e'],
            ['Oro\Bundle\TestBundle\Entity\OtherTest', 'test', 'other_test_14ac1fd7'],
            ['Acme\Bundle\TestBundle\Entity\Test', null, 'test_77981b51'],
            ['Acme\Bundle\TestBundle\Entity\Test', 'test', 'test_21bc9fd6'],
            ['Acme\Bundle\TestBundle\Entity\OtherTest', null, 'other_test_3efb8e13'],
            ['Acme\Bundle\TestBundle\Entity\OtherTest', 'test', 'other_test_8ca3d713'],
            ['Test', null, 'test_784dd132'],
            ['Test', 'test', 'test_4c5b140f'],
            ['OtherTest', null, 'other_test_f54366f8'],
            ['OtherTest', 'test', 'other_test_4ee028ce'],
        ];
    }

    public function testBuildRelationKey(): void
    {
        $this->assertEquals(
            'manyToOne|Test\Entity|Test\TargetEntity|testField',
            ExtendHelper::buildRelationKey('Test\Entity', 'testField', 'manyToOne', 'Test\TargetEntity')
        );
    }

    public function testToggleRelationKeyWhenOwningAndTargetEntitiesAreNotEqual(): void
    {
        $this->assertEquals(
            'manyToOne|Test\Entity|Test\TargetEntity|testField',
            ExtendHelper::toggleRelationKey('manyToOne|Test\Entity|Test\TargetEntity|testField')
        );
    }

    public function testToggleRelationKeyWhenOwningAndTargetEntitiesAreNotEqualAndGivenKeyHasInverseSuffix(): void
    {
        $this->assertEquals(
            'manyToOne|Test\Entity|Test\TargetEntity|testField|inverse',
            ExtendHelper::toggleRelationKey('manyToOne|Test\Entity|Test\TargetEntity|testField|inverse')
        );
    }

    public function testToggleRelationKeyWhenOwningAndTargetEntitiesAreEqualAndGivenKeyIsOwningSideKey(): void
    {
        $this->assertEquals(
            'manyToOne|Test\Entity|Test\Entity|testField|inverse',
            ExtendHelper::toggleRelationKey('manyToOne|Test\Entity|Test\Entity|testField')
        );
    }

    public function testToggleRelationKeyWhenOwningAndTargetEntitiesAreEqualAndGivenKeyIsInverseSideKey(): void
    {
        $this->assertEquals(
            'manyToOne|Test\Entity|Test\Entity|testField',
            ExtendHelper::toggleRelationKey('manyToOne|Test\Entity|Test\Entity|testField|inverse')
        );
    }

    public function testToggleRelationKeyWhenOwningAndTargetEntitiesAreEqualButKeyIsNotSupported(): void
    {
        $this->assertEquals(
            'manyToOne|Test\Entity|Test\Entity|testField|other',
            ExtendHelper::toggleRelationKey('manyToOne|Test\Entity|Test\Entity|testField|other')
        );
    }

    public function testToggleRelationKeyWhenGivenKeyIsInvalid(): void
    {
        $this->assertEquals(
            'someInvalidValue',
            ExtendHelper::toggleRelationKey('someInvalidValue')
        );
    }

    public function testGetRelationType(): void
    {
        $this->assertEquals(
            'manyToOne',
            ExtendHelper::getRelationType('manyToOne|Test\Entity|Test\TargetEntity|testField')
        );
    }

    public function testGetRelationTypeForInverseRelationKey(): void
    {
        $this->assertEquals(
            'manyToOne',
            ExtendHelper::getRelationType('manyToOne|Test\Entity|Test\Entity|testField|inverse')
        );
    }

    /**
     * @dataProvider invalidRelationKeysForGetRelationType
     */
    public function testGetRelationTypeForInvalidRelationKey(?string $relationKey): void
    {
        $this->assertNull(ExtendHelper::getRelationType($relationKey));
    }

    public function invalidRelationKeysForGetRelationType(): array
    {
        return [
            [null],
            ['manyToOne'],
            ['manyToOne|Test\Entity|Test\Entity'],
            ['manyToOne|Test\Entity|Test\Entity|testField|inverse|other'],
        ];
    }

    /**
     * @dataProvider buildEnumCodeProvider
     */
    public function testBuildEnumCode(string $enumName, string $expectedEnumCode): void
    {
        $this->assertEquals(
            $expectedEnumCode,
            ExtendHelper::buildEnumCode($enumName)
        );
    }

    public static function buildEnumCodeProvider(): array
    {
        return [
            ['test', 'test'],
            ['Test', 'test'],
            ['test123', 'test123'],
            ['test 123', 'test_123'],
            [' test 123 ', 'test_123'],
            ['test_123', 'test_123'],
            ['test___123', 'test_123'],
            ['test-123', 'test_123'],
            ['test---123', 'test_123'],
            ['test---___123', 'test_123'],
            ['test- - - _ _ _ 123', 'test_123'],
            ['test \/()[]~!@#$%^&*_+,.`', 'test_'],
        ];
    }

    /**
     * @dataProvider buildEnumCodeForInvalidEnumNameProvider
     */
    public function testBuildEnumCodeForInvalidEnumName(string $enumName): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ExtendHelper::buildEnumCode($enumName);
    }

    /**
     * @dataProvider buildEnumCodeForInvalidEnumNameProvider
     */
    public function testBuildEnumCodeForInvalidEnumNameIgnoreException(string $enumValueName): void
    {
        $this->assertSame(
            '',
            ExtendHelper::buildEnumCode($enumValueName, false)
        );
    }

    public static function buildEnumCodeForInvalidEnumNameProvider(): array
    {
        return [
            [''],
            ['_'],
            ['-'],
            ['__'],
            ['_ _'],
            [' \/()[]~!@#$%^&*+-'],
        ];
    }

    /**
     * @dataProvider generateEnumCodeProvider
     */
    public function testGenerateEnumCode(
        string $entityClassName,
        string $fieldName,
        ?int $maxEnumCodeSize,
        string $expectedEnumCode
    ): void {
        $this->assertEquals(
            $expectedEnumCode,
            ExtendHelper::generateEnumCode($entityClassName, $fieldName, $maxEnumCodeSize)
        );
    }

    public static function generateEnumCodeProvider(): array
    {
        return [
            ['Test\Entity', 'field1', null, 'entity_field1_489d47b1'],
            ['Test\Entity', 'testField1', null, 'entity_test_field1_3940a34c'],
            ['Test\Entity', 'test_field_1', null, 'entity_test_field_1_7e9aa412'],
            ['Test\Entity', 'test_field_1', 21, 'entity_7e9aa412'],
            ['Test\Entity1234567', 'testField1', 21, 'enum_de837b64_7d0f22a1'],
        ];
    }

    public function testGenerateEnumCodeForEmptyClassName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$entityClassName must not be empty.');

        ExtendHelper::generateEnumCode('', 'testField');
    }

    public function testGenerateEnumCodeForEmptyFieldName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$fieldName must not be empty.');

        ExtendHelper::generateEnumCode('Test\Entity', '');
    }

    /**
     * @dataProvider buildEnumValueIdProvider
     */
    public function testBuildEnumValueId(string $enumValueName, string $expectedEnumValueId): void
    {
        $enumValueId = ExtendHelper::buildEnumInternalId($enumValueName);
        $this->assertEquals(
            $expectedEnumValueId,
            $enumValueId
        );
        $this->assertTrue(
            strlen($enumValueId) <= ExtendHelper::MAX_ENUM_INTERNAL_ID_LENGTH,
            sprintf(
                'The enum value id must be less or equal than %d characters',
                ExtendHelper::MAX_ENUM_INTERNAL_ID_LENGTH
            )
        );
    }

    public static function buildEnumValueIdProvider(): array
    {
        return [
            ['0', '0'],
            ['10', '10'],
            ['1.0', '10'],
            ['test', 'test'],
            ['Test', 'test'],
            ['test123', 'test123'],
            ['test 123', 'test_123'],
            [' test 123 ', 'test_123'],
            ['test_123', 'test_123'],
            ['test___123', 'test_123'],
            ['test-123', 'test_123'],
            ['test---123', 'test_123'],
            ['test---___123', 'test_123'],
            ['test- - - _ _ _ 123', 'test_123'],
            ['test \/()[]~!@#$%^&*_+`', 'test_'],
            ['01234567890123456789012345678901', '01234567890123456789012345678901'],
            ['012345678901234567890123456789012', '012345678901234567890123_226f1a9'],
            ['sed do eiusmod tempor incididunt ut labore et dolore magna aliqua', 'sed_do_eiusmod_tempor_i_a5e72088'],
            ['broken_value_nameºss', '5eff4cfd']
        ];
    }

    public function testBuildEnumValueIdWithCustomLocale(): void
    {
        $locale = setlocale(LC_CTYPE, 0);
        if (false === $locale) {
            self::markTestSkipped('The locale functionality is not implemented on your platform.');
        }

        setlocale(LC_CTYPE, 'en_US.UTF-8');
        try {
            $result = ExtendHelper::buildEnumInternalId('broken_value_nameºss');
        } finally {
            setlocale(LC_CTYPE, $locale);
        }
        $this->assertEquals('5eff4cfd', $result);
    }

    /**
     * @dataProvider buildEnumValueIdForInvalidEnumValueNameProvider
     */
    public function testBuildEnumValueIdForInvalidEnumValueName(string $enumValueName): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ExtendHelper::buildEnumInternalId($enumValueName);
    }

    /**
     * @dataProvider buildEnumValueIdForInvalidEnumValueNameProvider
     */
    public function testBuildEnumValueIdForInvalidEnumValueNameIgnoreException(string $enumValueName): void
    {
        $this->assertSame(
            '',
            ExtendHelper::buildEnumInternalId($enumValueName, false)
        );
    }

    public function testGetEnumInternalId(): void
    {
        $this->assertEquals(
            'option1',
            ExtendHelper::getEnumInternalId('test_enum.option1')
        );
    }

    public static function buildEnumValueIdForInvalidEnumValueNameProvider(): array
    {
        return [
            [''],
            ['_'],
            ['-'],
            ['__'],
            ['_ _'],
            [' \/()[]~!@#$%^&*+-'],
        ];
    }

    /**
     * @dataProvider isEnumOptionEntityDataProvider
     */
    public function testIsEnumOptionEntity(string $className, bool $result): void
    {
        $this->assertEquals($result, ExtendHelper::isOutdatedEnumOptionEntity($className));
    }

    public static function isEnumOptionEntityDataProvider(): array
    {
        return [
            ['Extend\Entity\EV_Class', true],
            ['Extend\Entity\Class', false],
            ['Acme\Class', false]
        ];
    }

    /**
     * @dataProvider getEnumOptionEntityClassNameDataProvider
     */
    public function testGetOutdatedEnumOptionEntityClassName(string $enumCode, string $className): void
    {
        $this->assertEquals($className, ExtendHelper::getOutdatedEnumOptionClassName($enumCode));
    }

    public static function getEnumOptionEntityClassNameDataProvider(): array
    {
        return [
            ['test', 'Extend\Entity\EV_Test'],
            ['test_class', 'Extend\Entity\EV_Test_Class'],
            ['Test_Class', 'Extend\Entity\EV_Test_Class']
        ];
    }

    public function testGetEnumCode(): void
    {
        $this->assertEquals('test_class', ExtendHelper::getEnumCode('Extend\Entity\EV_Test_Class'));
    }

    public function testGetMultiEnumSnapshotFieldName(): void
    {
        $this->assertEquals(
            'testFieldSnapshot',
            ExtendHelper::getMultiEnumSnapshotFieldName('testField')
        );
    }

    /**
     * @dataProvider getEnumTranslationKeyProvider
     */
    public function testGetEnumTranslationKey(
        string $propertyName,
        string $enumCode,
        ?string $fieldName,
        string $expected
    ): void {
        $this->assertEquals(
            $expected,
            ExtendHelper::getEnumTranslationKey($propertyName, $enumCode, $fieldName)
        );
    }

    public static function getEnumTranslationKeyProvider(): array
    {
        return [
            ['label', 'test_enum', null, 'oro.entityextend.enums.test_enum.entity_label'],
            ['label', 'test_enum', 'testField', 'oro.entityextend.enumvalue.testField.label'],
        ];
    }

    /**
     * @dataProvider isCustomEntityProvider
     */
    public function testIsCustomEntity(string $className, bool $expected): void
    {
        $this->assertEquals(
            $expected,
            ExtendHelper::isCustomEntity($className)
        );
    }

    public static function isCustomEntityProvider(): array
    {
        return [
            ['Extend\Entity\Test', true],
            ['Acme\Bundle\TestBundle\Entity\Test', false],
        ];
    }

    /**
     * @dataProvider getShortClassNameProvider
     */
    public function testGetShortClassName(string $className, string $expected): void
    {
        $this->assertEquals(
            $expected,
            ExtendHelper::getShortClassName($className)
        );
    }

    public static function getShortClassNameProvider(): array
    {
        return [
            ['Oro\Bundle\TestBundle\Entity\Test', 'Test'],
            ['Acme\Bundle\TestBundle\Entity\Test', 'Test'],
            ['Test', 'Test'],
        ];
    }

    public function testGetParentClassNameForEnumOptionEntity(): void
    {
        $this->assertEquals(EnumOption::class, ExtendHelper::getParentClassName('Extend\Entity\EV_Test'));
    }

    public function testGetParentClassNameWhenNoParentClass(): void
    {
        $this->assertNull(ExtendHelper::getParentClassName(TestClass::class));
    }

    public function testGetParentClassNameWhenThereIsParentClass(): void
    {
        $this->assertEquals(Bundle::class, ExtendHelper::getParentClassName(TestBundle1::class));
    }

    /**
     * @dataProvider getExtendEntityProxyClassNameProvider
     */
    public function testGetExtendEntityProxyClassName(string $className, string $expected): void
    {
        $this->assertEquals(
            $expected,
            ExtendHelper::getExtendEntityProxyClassName($className)
        );
    }

    public static function getExtendEntityProxyClassNameProvider(): array
    {
        return [
            [
                'Oro\Bundle\EntityExtendBundle\Model\ExtendTestClass',
                ExtendHelper::ENTITY_NAMESPACE . 'EX_OroEntityExtendBundle_TestClass'
            ],
            [
                'Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\ExtendTestClass',
                ExtendHelper::ENTITY_NAMESPACE . 'EX_OroEntityExtendBundle_Tests_Unit_Fixtures_TestClass'
            ],
        ];
    }

    /**
     * @dataProvider updatedPendingValueDataProvider
     */
    public function testUpdatedPendingValue(int|array $currentVal, array $changeSet, int|array $expectedResult): void
    {
        $this->assertEquals($expectedResult, ExtendHelper::updatedPendingValue($currentVal, $changeSet));
    }

    public function updatedPendingValueDataProvider(): array
    {
        return [
            'scalar value' => [
                1,
                [
                    1,
                    2,
                ],
                2,
            ],
            'array value' => [
                ['v1', 'v2', 'v3'],
                [
                    ['v1', 'v2'],
                    ['v1', 'v4'],
                ],
                ['v1', 'v3', 'v4'],
            ],
        ];
    }
}
