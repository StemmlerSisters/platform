<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Attribute\Type;

use Oro\Bundle\EntityConfigBundle\Attribute\Type\AttributeTypeInterface;
use Oro\Bundle\EntityConfigBundle\Attribute\Type\MultiEnumAttributeType;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestEnumValue;

class MultiEnumAttributeTypeTest extends AttributeTypeTestCase
{
    #[\Override]
    protected function getAttributeType(): AttributeTypeInterface
    {
        return new MultiEnumAttributeType();
    }

    #[\Override]
    public function configurationMethodsDataProvider(): array
    {
        return [
            ['isSearchable' => true, 'isFilterable' => true, 'isSortable' => false]
        ];
    }

    public function testGetSearchableValue(): void
    {
        $value1 = new TestEnumValue('test', 'name1', 'id1', 101);
        $value2 = new TestEnumValue('test', 'name2', 'id2', 102);

        $this->assertSame(
            'name1 name2',
            $this->getAttributeType()->getSearchableValue($this->attribute, [$value1, $value2], $this->localization)
        );
    }

    public function testGetSearchableValueTraversableException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be an array or Traversable, [string] given');

        $this->getAttributeType()->getSearchableValue($this->attribute, '', $this->localization);
    }

    public function testGetSearchableValueValueException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Value must be instance of "Oro\Bundle\EntityExtendBundle\Entity\EnumOptionInterface", "integer" given'
        );

        $this->getAttributeType()->getSearchableValue($this->attribute, [42], $this->localization);
    }

    public function testGetFilterableValue(): void
    {
        $value1 = new TestEnumValue('test', 'Test1', 'id1', 101);
        $value2 = new TestEnumValue('test', 'Test2', 'id2', 102);

        $this->assertSame(
            [
                self::FIELD_NAME . '_enum.id1' => 1,
                self::FIELD_NAME . '_enum.id2' => 1
            ],
            $this->getAttributeType()->getFilterableValue($this->attribute, [$value1, $value2], $this->localization)
        );
    }

    public function testGetFilterableValueTraversableException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be an array or Traversable, [string] given');

        $this->getAttributeType()->getFilterableValue($this->attribute, '', $this->localization);
    }

    public function testGetFilterableValueValueException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Value must be instance of "Oro\Bundle\EntityExtendBundle\Entity\EnumOptionInterface", "integer" given'
        );

        $this->getAttributeType()->getFilterableValue($this->attribute, [42], $this->localization);
    }

    public function testGetSortableValue(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not supported');

        $this->getAttributeType()->getSortableValue($this->attribute, true, $this->localization);
    }
}
