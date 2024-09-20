<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Util;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Oro\Bundle\ApiBundle\Util\ComparisonExpressionsVisitor;

class ComparisonExpressionsVisitorTest extends \PHPUnit\Framework\TestCase
{
    private ComparisonExpressionsVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new ComparisonExpressionsVisitor();
    }

    public function testWalkComparison(): void
    {
        self::assertSame([], $this->visitor->getComparisons());

        $expr = new Comparison('fieldName1', Comparison::EQ, 'value');
        $this->visitor->walkComparison($expr);

        self::assertSame(
            [$expr],
            $this->visitor->getComparisons()
        );
    }

    public function testWalkCompositeExpression(): void
    {
        self::assertSame([], $this->visitor->getComparisons());

        $expr1 = new Comparison('fieldName1', Comparison::EQ, 'value');
        $expr2 = new Comparison('fieldName2', Comparison::EQ, 'value');
        $this->visitor->walkCompositeExpression(
            new CompositeExpression(CompositeExpression::TYPE_AND, [$expr1, $expr2])
        );

        self::assertSame(
            [$expr1, $expr2],
            $this->visitor->getComparisons()
        );
    }
}
