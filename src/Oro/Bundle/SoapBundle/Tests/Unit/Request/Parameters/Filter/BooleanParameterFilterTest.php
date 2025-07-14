<?php

namespace Oro\Bundle\SoapBundle\Tests\Unit\Request\Parameters\Filter;

use Oro\Bundle\SoapBundle\Request\Parameters\Filter\BooleanParameterFilter;
use PHPUnit\Framework\TestCase;

class BooleanParameterFilterTest extends TestCase
{
    /**
     * @dataProvider filterDataProvider
     */
    public function testFilter($expected, $rawValue): void
    {
        $filter = new BooleanParameterFilter();

        $this->assertSame($expected, $filter->filter($rawValue, null));
    }

    public function filterDataProvider(): array
    {
        return [
            [false, 'false'],
            [false, 'no'],
            [false, 'off'],
            [false, false],
            [false, 0],
            [false, '0'],
            [false, ''],
            [null, '123'],
            [null, 123],
            [false, null],
            [true, 'true'],
            [true, 'yes'],
            [true, 'on'],
            [true, true],
            [true, 1],
            [true, '1'],
        ];
    }
}
