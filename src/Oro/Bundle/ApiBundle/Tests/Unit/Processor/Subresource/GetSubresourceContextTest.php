<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource;

use Oro\Bundle\ApiBundle\Processor\Subresource\GetSubresource\GetSubresourceContext;
use Oro\Bundle\ApiBundle\Provider\ConfigProvider;
use Oro\Bundle\ApiBundle\Provider\MetadataProvider;
use PHPUnit\Framework\TestCase;

class GetSubresourceContextTest extends TestCase
{
    private GetSubresourceContext $context;

    #[\Override]
    protected function setUp(): void
    {
        $this->context = new GetSubresourceContext(
            $this->createMock(ConfigProvider::class),
            $this->createMock(MetadataProvider::class)
        );
    }

    private function assertTotalCountCallback(callable $totalCountCallback, int $expectedResult): void
    {
        $this->context->setTotalCountCallback($totalCountCallback);
        self::assertSame($totalCountCallback, $this->context->getTotalCountCallback());
        self::assertSame($totalCountCallback, $this->context->get('totalCountCallback'));
        self::assertSame($expectedResult, call_user_func($this->context->getTotalCountCallback()));
    }

    public function calculateTotalCount(): int
    {
        return 123;
    }

    public function testTotalCountCallback(): void
    {
        self::assertNull($this->context->getTotalCountCallback());

        $this->assertTotalCountCallback(
            function (): int {
                return 123;
            },
            123
        );

        $this->assertTotalCountCallback(
            new class() {
                public function __invoke(): int
                {
                    return 123;
                }
            },
            123
        );

        $this->assertTotalCountCallback([$this, 'calculateTotalCount'], 123);
    }
}
