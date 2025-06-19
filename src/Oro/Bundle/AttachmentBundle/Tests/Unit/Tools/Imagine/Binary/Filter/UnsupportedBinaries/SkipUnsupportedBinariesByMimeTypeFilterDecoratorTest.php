<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Tools\Imagine\Binary\Filter\UnsupportedBinaries;

use Liip\ImagineBundle\Model\Binary;
use Oro\Bundle\AttachmentBundle\Tools\Imagine\Binary\Filter\ImagineBinaryFilterInterface;
use Oro\Bundle\AttachmentBundle\Tools\Imagine\Binary\Filter\UnsupportedBinaries;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SkipUnsupportedBinariesByMimeTypeFilterDecoratorTest extends TestCase
{
    private ImagineBinaryFilterInterface&MockObject $decoratedFilter;
    private UnsupportedBinaries\SkipUnsupportedBinariesByMimeTypeFilterDecorator $filterDecorator;

    #[\Override]
    protected function setUp(): void
    {
        $this->decoratedFilter = $this->createMock(ImagineBinaryFilterInterface::class);

        $this->filterDecorator = new UnsupportedBinaries\SkipUnsupportedBinariesByMimeTypeFilterDecorator(
            $this->decoratedFilter,
            ['image/png']
        );
    }

    public function testApplyFilterSupported(): void
    {
        $binary = new Binary('sample_binary', 'image/jpg');
        $filter = 'product_medium';
        $runtimeConfig = ['sample_key' => 'sample_value'];

        $resultBinary = new Binary('sample_binary', 'image/jpg');
        $this->decoratedFilter->expects(self::once())
            ->method('applyFilter')
            ->with($binary, $filter, $runtimeConfig)
            ->willReturn($resultBinary);

        self::assertSame($resultBinary, $this->filterDecorator->applyFilter($binary, $filter, $runtimeConfig));
    }

    public function testApplyFilterNotSupported(): void
    {
        $binary = new Binary('sample_binary', 'image/png');
        $filter = 'product_medium';

        $this->decoratedFilter->expects(self::never())
            ->method('applyFilter');

        $this->filterDecorator->applyFilter($binary, $filter);
    }

    public function testApplySupported(): void
    {
        $binary = new Binary('sample_binary', 'image/jpg');
        $runtimeConfig = ['sample_key' => 'sample_value'];

        $resultBinary = new Binary('sample_binary', 'image/jpg');
        $this->decoratedFilter->expects(self::once())
            ->method('apply')
            ->with($binary, $runtimeConfig)
            ->willReturn($resultBinary);

        self::assertSame($resultBinary, $this->filterDecorator->apply($binary, $runtimeConfig));
    }

    public function testApplyNotSupported(): void
    {
        $binary = new Binary('sample_binary', 'image/png');

        $this->decoratedFilter->expects(self::never())
            ->method('apply');

        $this->filterDecorator->apply($binary, ['sample_key' => 'sample_value']);
    }
}
