<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Attribute\Type;

use Oro\Bundle\EntityConfigBundle\Attribute\Type\AttributeTypeInterface;
use Oro\Bundle\EntityConfigBundle\Attribute\Type\DateAttributeType;

class DateAttributeTypeTest extends AttributeTypeTestCase
{
    #[\Override]
    protected function getAttributeType(): AttributeTypeInterface
    {
        return new DateAttributeType();
    }

    #[\Override]
    public function configurationMethodsDataProvider(): array
    {
        return [
            ['isSearchable' => false, 'isFilterable' => false, 'isSortable' => true]
        ];
    }

    public function testGetSearchableValue(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not supported');

        $this->getAttributeType()->getSearchableValue($this->attribute, new \DateTime(), $this->localization);
    }

    public function testGetFilterableValue(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not supported');

        $this->getAttributeType()->getFilterableValue($this->attribute, new \DateTime(), $this->localization);
    }

    public function testGetSortableValue(): void
    {
        $date = new \DateTime('2017-01-01 12:00:00', new \DateTimeZone('America/Los_Angeles'));

        $this->assertEquals(
            $date,
            $this->getAttributeType()->getSortableValue($this->attribute, $date, $this->localization)
        );
    }

    public function testGetSortableValueNullValue(): void
    {
        $this->assertNull($this->getAttributeType()->getSortableValue($this->attribute, null, $this->localization));
    }

    public function testGetSortableValueException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be instance of "DateTime", "stdClass" given');

        $this->getAttributeType()->getSortableValue($this->attribute, new \stdClass(), $this->localization);
    }
}
