<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Filter;

use Oro\Bundle\ApiBundle\Filter\FilterValue;
use Oro\Bundle\ApiBundle\Filter\FilterValueAccessor;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class FilterValueAccessorTest extends TestCase
{
    private FilterValueAccessor $accessor;

    #[\Override]
    protected function setUp(): void
    {
        $this->accessor = new FilterValueAccessor();
    }

    public function testAddNewFilterValueWithoutSourceKey(): void
    {
        $filterValue = new FilterValue('prm1', 'val1', 'eq');
        $this->accessor->set('prm1', $filterValue);
        self::assertSame([$filterValue], $this->accessor->get('prm1'));
        self::assertSame(['prm1' => [$filterValue]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['prm1' => [$filterValue]], $this->accessor->getGroup('prm1'), 'getGroup');

        self::assertEquals(
            '',
            $this->accessor->getQueryString()
        );
    }

    public function testAddNewFilterValueWithSourceKey(): void
    {
        $filterValue = FilterValue::createFromSource('prm1', 'prm1', 'val1', 'eq');
        $this->accessor->set('prm1', $filterValue);
        self::assertSame([$filterValue], $this->accessor->get('prm1'));
        self::assertSame(['prm1' => [$filterValue]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['prm1' => [$filterValue]], $this->accessor->getGroup('prm1'), 'getGroup');

        self::assertEquals(
            'prm1=val1',
            $this->accessor->getQueryString()
        );
    }

    public function testAddNewFilterValueWithoutOperator(): void
    {
        $filterValue = FilterValue::createFromSource('prm1', 'prm1', 'val1');
        $this->accessor->set('prm1', $filterValue);
        self::assertSame([$filterValue], $this->accessor->get('prm1'));
        self::assertSame(['prm1' => [$filterValue]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['prm1' => [$filterValue]], $this->accessor->getGroup('prm1'), 'getGroup');
        self::assertSame($filterValue, $this->accessor->getOne('prm1'));

        self::assertEquals(
            'prm1=val1',
            $this->accessor->getQueryString()
        );
    }

    public function testAddNewFilterValueWithCustomOperator(): void
    {
        $filterValue = FilterValue::createFromSource('prm1', 'prm1', 'val1', 'contains');
        $this->accessor->set('prm1', $filterValue);
        self::assertSame([$filterValue], $this->accessor->get('prm1'));
        self::assertSame(['prm1' => [$filterValue]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['prm1' => [$filterValue]], $this->accessor->getGroup('prm1'), 'getGroup');
        self::assertSame($filterValue, $this->accessor->getOne('prm1'));

        self::assertEquals(
            'prm1%5Bcontains%5D=val1',
            $this->accessor->getQueryString()
        );
    }

    public function testAddNewFilterValuesWithSameKeyButDifferentOperators(): void
    {
        $filterValue1 = FilterValue::createFromSource('prm1', 'prm1', 'val1');
        $filterValue2 = FilterValue::createFromSource('prm1', 'prm1', 'val2', 'contains');
        $this->accessor->set('prm1', $filterValue1);
        $this->accessor->set('prm1', $filterValue2);
        self::assertSame([$filterValue1, $filterValue2], $this->accessor->get('prm1'));
        self::assertSame(['prm1' => [$filterValue1, $filterValue2]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['prm1' => [$filterValue1, $filterValue2]], $this->accessor->getGroup('prm1'), 'getGroup');
        self::assertSame($filterValue2, $this->accessor->getOne('prm1'));

        self::assertEquals(
            'prm1=val1&prm1%5Bcontains%5D=val2',
            $this->accessor->getQueryString()
        );
    }

    public function testAddNewGroupedFilterValueWithoutSourceKey(): void
    {
        $filterValue = new FilterValue('path', 'val1', 'eq');
        $this->accessor->set('group[path]', $filterValue);
        self::assertSame([$filterValue], $this->accessor->get('group[path]'));
        self::assertSame(['group[path]' => [$filterValue]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['group[path]' => [$filterValue]], $this->accessor->getGroup('group'), 'getGroup');

        self::assertEquals(
            '',
            $this->accessor->getQueryString()
        );
    }

    public function testAddNewGroupedFilterValueWithSourceKey(): void
    {
        $filterValue = FilterValue::createFromSource('group[path]', 'path', 'val1', 'eq');
        $this->accessor->set('group[path]', $filterValue);
        self::assertSame([$filterValue], $this->accessor->get('group[path]'));
        self::assertSame(['group[path]' => [$filterValue]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['group[path]' => [$filterValue]], $this->accessor->getGroup('group'), 'getGroup');

        self::assertEquals(
            'group%5Bpath%5D=val1',
            $this->accessor->getQueryString()
        );
    }

    public function testAddNewGroupedFilterValueWithoutOperator(): void
    {
        $filterValue = FilterValue::createFromSource('group[path]', 'path', 'val1');
        $this->accessor->set('group[path]', $filterValue);
        self::assertSame([$filterValue], $this->accessor->get('group[path]'));
        self::assertSame(['group[path]' => [$filterValue]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['group[path]' => [$filterValue]], $this->accessor->getGroup('group'), 'getGroup');

        self::assertEquals(
            'group%5Bpath%5D=val1',
            $this->accessor->getQueryString()
        );
    }

    public function testAddNewGroupedFilterValueWithCustomOperator(): void
    {
        $filterValue = FilterValue::createFromSource('group[path]', 'path', 'val1', 'contains');
        $this->accessor->set('group[path]', $filterValue);
        self::assertSame([$filterValue], $this->accessor->get('group[path]'));
        self::assertSame(['group[path]' => [$filterValue]], $this->accessor->getAll(), 'getAll');
        self::assertSame(['group[path]' => [$filterValue]], $this->accessor->getGroup('group'), 'getGroup');

        self::assertEquals(
            'group%5Bpath%5D%5Bcontains%5D=val1',
            $this->accessor->getQueryString()
        );
    }

    public function testOverrideExistingFilterValue(): void
    {
        $existingFilterValue = FilterValue::createFromSource('prm1', 'prm1', 'oldValue', 'eq');
        $this->accessor->set('prm1', $existingFilterValue);
        self::assertSame([$existingFilterValue], $this->accessor->get('prm1'));

        $this->accessor->set('prm1', new FilterValue('prm1', 'newValue', 'eq'));

        $expectedFilterValue = new FilterValue('prm1', 'newValue', 'eq');
        $expectedFilterValue->setSource($existingFilterValue);
        self::assertEquals([$expectedFilterValue], $this->accessor->get('prm1'));
        self::assertEquals(['prm1' => [$expectedFilterValue]], $this->accessor->getAll(), 'getAll');
        self::assertEquals(['prm1' => [$expectedFilterValue]], $this->accessor->getGroup('prm1'), 'getGroup');

        self::assertEquals(
            'prm1=oldValue',
            $this->accessor->getQueryString()
        );
    }

    public function testOverrideExistingGroupedFilterValue(): void
    {
        $existingFilterValue = FilterValue::createFromSource('group[path]', 'path', 'oldValue', 'eq');
        $this->accessor->set('group[path]', $existingFilterValue);
        self::assertSame([$existingFilterValue], $this->accessor->get('group[path]'));

        $this->accessor->set('group[path]', new FilterValue('path', 'neValue', 'eq'));

        $expectedFilterValue = new FilterValue('path', 'neValue', 'eq');
        $expectedFilterValue->setSource($existingFilterValue);
        self::assertEquals([$expectedFilterValue], $this->accessor->get('group[path]'));
        self::assertEquals(['group[path]' => [$expectedFilterValue]], $this->accessor->getAll(), 'getAll');
        self::assertEquals(['group[path]' => [$expectedFilterValue]], $this->accessor->getGroup('group'), 'getGroup');

        self::assertEquals(
            'group%5Bpath%5D=oldValue',
            $this->accessor->getQueryString()
        );
    }

    public function testRemoveExistingFilterValueViaSetMethod(): void
    {
        $existingFilterValue = FilterValue::createFromSource('prm1', 'prm1', 'val1', 'eq');
        $this->accessor->set('prm1', $existingFilterValue);
        self::assertSame([$existingFilterValue], $this->accessor->get('prm1'));

        // test override existing filter value
        $this->accessor->set('prm1', null);
        self::assertCount(0, $this->accessor->get('prm1'));
        self::assertCount(0, $this->accessor->getAll(), 'getAll');
        self::assertCount(0, $this->accessor->getGroup('prm1'), 'getGroup');

        self::assertEquals(
            '',
            $this->accessor->getQueryString()
        );
    }

    public function testRemoveExistingGroupedFilterValueViaSetMethod(): void
    {
        $existingFilterValue = FilterValue::createFromSource('group[path]', 'path', 'val1', 'eq');
        $this->accessor->set('group[path]', $existingFilterValue);
        self::assertSame([$existingFilterValue], $this->accessor->get('group[path]'));

        // test override existing filter value
        $this->accessor->set('group[path]', null);
        self::assertCount(0, $this->accessor->get('group[path]'));
        self::assertCount(0, $this->accessor->getAll(), 'getAll');
        self::assertCount(0, $this->accessor->getGroup('group'), 'getGroup');

        self::assertEquals(
            '',
            $this->accessor->getQueryString()
        );
    }

    public function testRemoveExistingFilterValueViaRemoveMethod(): void
    {
        $existingFilterValue = FilterValue::createFromSource('prm1', 'prm1', 'val1', 'eq');
        $this->accessor->set('prm1', $existingFilterValue);
        self::assertSame([$existingFilterValue], $this->accessor->get('prm1'));

        // test remove existing filter value by key
        $this->accessor->remove('prm1');
        self::assertCount(0, $this->accessor->get('prm1'));
        self::assertCount(0, $this->accessor->getAll(), 'getAll');
        self::assertCount(0, $this->accessor->getGroup('prm1'), 'getGroup');

        self::assertEquals(
            '',
            $this->accessor->getQueryString()
        );
    }

    public function testRemoveExistingGroupedFilterValueViaRemoveMethod(): void
    {
        $existingFilterValue = FilterValue::createFromSource('group[path]', 'path', 'val1', 'eq');
        $this->accessor->set('group[path]', $existingFilterValue);
        self::assertSame([$existingFilterValue], $this->accessor->get('group[path]'));

        // test remove existing filter value by key
        $this->accessor->remove('group[path]');
        self::assertCount(0, $this->accessor->get('group[path]'));
        self::assertCount(0, $this->accessor->getAll(), 'getAll');
        self::assertCount(0, $this->accessor->getGroup('group'), 'getGroup');

        self::assertEquals(
            '',
            $this->accessor->getQueryString()
        );
    }

    public function testDefaultGroup(): void
    {
        $filterValue1 = FilterValue::createFromSource('filter1', 'filter1', 'val1', 'eq');
        $this->accessor->set('filter1', $filterValue1);
        self::assertSame([$filterValue1], $this->accessor->get('filter1'));

        $filterValue2 = FilterValue::createFromSource('filter[filter2]', 'filter2', 'val2', 'eq');
        $this->accessor->set('filter[filter2]', $filterValue2);
        self::assertSame([$filterValue2], $this->accessor->get('filter[filter2]'));

        self::assertNull($this->accessor->getDefaultGroupName());

        $this->accessor->setDefaultGroupName('filter');
        self::assertEquals('filter', $this->accessor->getDefaultGroupName());

        self::assertTrue($this->accessor->has('filter1'));
        self::assertFalse($this->accessor->has('filter[filter1]'));
        self::assertTrue($this->accessor->has('filter2'));
        self::assertTrue($this->accessor->has('filter[filter2]'));

        self::assertSame([$filterValue1], $this->accessor->get('filter1'));
        self::assertCount(0, $this->accessor->get('filter[filter1]'));
        self::assertSame([$filterValue2], $this->accessor->get('filter2'));
        self::assertSame([$filterValue2], $this->accessor->get('filter[filter2]'));

        self::assertEquals(
            'filter1=val1'
            . '&filter%5Bfilter2%5D=val2',
            $this->accessor->getQueryString()
        );
    }
}
