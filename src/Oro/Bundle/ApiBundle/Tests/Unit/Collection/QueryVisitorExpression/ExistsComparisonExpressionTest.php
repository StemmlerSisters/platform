<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Collection\QueryVisitorExpression;

use Oro\Bundle\ApiBundle\Collection\QueryExpressionVisitor;
use Oro\Bundle\ApiBundle\Collection\QueryVisitorExpression\ExistsComparisonExpression;
use Oro\Bundle\ApiBundle\Tests\Unit\Stub\FieldDqlExpressionProviderStub;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use PHPUnit\Framework\TestCase;

class ExistsComparisonExpressionTest extends TestCase
{
    public function testWalkComparisonExpressionForTrueValue(): void
    {
        $expression = new ExistsComparisonExpression();
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new FieldDqlExpressionProviderStub(),
            $this->createMock(EntityClassResolver::class)
        );
        $field = 'e.test';
        $expr = 'LOWER(e.test)';
        $parameterName = 'test_1';
        $value = true;

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        self::assertEquals($expr . ' IS NOT NULL', $result);
        self::assertEmpty($expressionVisitor->getParameters());
    }

    public function testWalkComparisonExpressionForFalseValue(): void
    {
        $expression = new ExistsComparisonExpression();
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new FieldDqlExpressionProviderStub(),
            $this->createMock(EntityClassResolver::class)
        );
        $field = 'e.test';
        $expr = 'LOWER(e.test)';
        $parameterName = 'test_1';
        $value = false;

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        self::assertEquals($expr . ' IS NULL', $result);
        self::assertEmpty($expressionVisitor->getParameters());
    }
}
