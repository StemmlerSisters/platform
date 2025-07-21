<?php

namespace Oro\Component\Layout\Tests\Unit;

use Oro\Component\Layout\ContextDataCollection;
use Oro\Component\Layout\LayoutContext;
use PHPUnit\Framework\TestCase;

class ContextDataCollectionTest extends TestCase
{
    private LayoutContext $context;
    private ContextDataCollection $collection;

    #[\Override]
    protected function setUp(): void
    {
        $this->context = new LayoutContext();

        $this->collection = new ContextDataCollection($this->context);
    }

    /**
     * @dataProvider valueDataProvider
     */
    public function testGetSetHasRemove(mixed $value): void
    {
        $this->assertFalse(
            $this->collection->has('test'),
            'Failed asserting that data do not exist'
        );
        $this->collection->set('test', $value);
        $this->assertTrue(
            $this->collection->has('test'),
            'Failed asserting that data exist'
        );
        $this->assertSame(
            $value,
            $this->collection->get('test'),
            'Failed asserting that added data equal to the value returned by "get" method'
        );

        $this->collection->remove('test');
        $this->assertFalse(
            $this->collection->has('test'),
            'Failed asserting that data were removed'
        );
    }

    public function valueDataProvider(): array
    {
        return [
            [null],
            [123],
            ['val'],
            [[]],
            [[1, 2, 3]],
            [new \stdClass()]
        ];
    }

    public function testSetDefault(): void
    {
        $this->context['data'] = 'data';

        $this->collection->setDefault(
            'test',
            function ($options) {
                return $options['data'];
            }
        );

        $this->assertEquals(
            ['test'],
            $this->collection->getKnownValues(),
            'Failed asserting that getKnownValues returns expected values'
        );
        $this->assertTrue(
            $this->collection->has('test'),
            'Failed asserting that data exist'
        );
        $this->assertSame(
            'data',
            $this->collection->get('test'),
            'Failed asserting that added data equal to the expected value'
        );

        $this->collection->set('test', 'updatedData');
        $this->assertEquals(
            ['test'],
            $this->collection->getKnownValues(),
            'Failed asserting that getKnownValues does not return duplicates'
        );
        $this->assertSame(
            'updatedData',
            $this->collection->get('test'),
            'Failed asserting that added data equal to the value returned by "get" method'
        );

        $this->collection->remove('test');
        $this->assertTrue(
            $this->collection->has('test'),
            'Failed asserting that default data exist after remove'
        );
        $this->assertSame(
            'data',
            $this->collection->get('test'),
            'Failed asserting that added data equal to the expected value after remove'
        );
    }

    public function testSetDefaultWhenDataCannotBeLoaded(): void
    {
        $this->collection->setDefault(
            'test',
            function () {
                throw new \BadMethodCallException();
            }
        );

        $this->assertFalse($this->collection->has('test'));
    }

    public function testSetDefaultScalar(): void
    {
        $this->collection->setDefault('test', 'data');
        $this->assertSame(
            'data',
            $this->collection->get('test'),
            'Failed asserting that added data equal to the expected value'
        );
    }

    public function testSetDefaultCallable(): void
    {
        $this->context['data'] = 'data';

        $this->collection->setDefault(
            'test',
            [$this, 'getTestDataValue']
        );

        $this->assertSame(
            'data',
            $this->collection->get('test'),
            'Failed asserting that added data equal to the expected value'
        );
    }

    public function getTestDataValue(LayoutContext $options): mixed
    {
        return $options['data'];
    }
}
