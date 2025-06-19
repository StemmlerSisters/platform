<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Collection\QueryVisitorExpression;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Tree\TreeListener;
use Oro\Bundle\ApiBundle\Collection\QueryExpressionVisitor;
use Oro\Bundle\ApiBundle\Collection\QueryVisitorExpression\NestedTreeComparisonExpression;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity;
use Oro\Bundle\ApiBundle\Tests\Unit\OrmRelatedTestCase;
use Oro\Bundle\ApiBundle\Tests\Unit\Stub\FieldDqlExpressionProviderStub;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use PHPUnit\Framework\MockObject\MockObject;

class NestedTreeComparisonExpressionTest extends OrmRelatedTestCase
{
    private TreeListener&MockObject $treeListener;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->treeListener = $this->createMock(TreeListener::class);
    }

    private function expectGetConfiguration(array $config): void
    {
        $this->treeListener->expects(self::any())
            ->method('getConfiguration')
            ->with(self::identicalTo($this->em), Entity\User::class)
            ->willReturn($config);
    }

    public function testWalkComparisonExpression(): void
    {
        $this->expectGetConfiguration([
            'root'  => 'rootField',
            'left'  => 'leftField',
            'right' => 'rightField'
        ]);
        $expression = new NestedTreeComparisonExpression(
            $this->treeListener,
            $this->doctrine
        );
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new FieldDqlExpressionProviderStub(),
            new EntityClassResolver($this->doctrine)
        );
        $field = '';
        $expr = $field;
        $parameterName = 'e_1';
        $value = 123;

        $qb = new QueryBuilder($this->em);
        $qb
            ->select('e')
            ->from(Entity\User::class, 'e')
            ->innerJoin('e.groups', 'groups');

        $expressionVisitor->setQuery($qb);
        $expressionVisitor->setQueryAliases(['e', 'groups']);
        $expressionVisitor->setQueryJoinMap(['groups' => 'groups']);

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        $expectedSubquery = 'SELECT e_subquery1'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\User e_subquery1'
            . ' INNER JOIN Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\User e_subquery1_criteria'
            . ' WITH e_subquery1_criteria = :e_1'
            . ' WHERE e_subquery1 = e'
            . ' AND ('
            . 'e_subquery1.rightField < e_subquery1_criteria.rightField'
            . ' AND e_subquery1.leftField > e_subquery1_criteria.leftField'
            . ' AND e_subquery1.rootField = e_subquery1_criteria.rootField)';

        self::assertEquals(
            new Expr\Func('EXISTS', [$expectedSubquery]),
            $result
        );
        self::assertEquals(
            [new Parameter($parameterName, $value)],
            $expressionVisitor->getParameters()
        );
    }

    public function testWalkComparisonExpressionForAssociation(): void
    {
        $this->treeListener->expects(self::any())
            ->method('getConfiguration')
            ->with(self::identicalTo($this->em), Entity\Category::class)
            ->willReturn([
                'root'  => 'rootField',
                'left'  => 'leftField',
                'right' => 'rightField'
            ]);
        $expression = new NestedTreeComparisonExpression(
            $this->treeListener,
            $this->doctrine
        );
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new FieldDqlExpressionProviderStub(),
            new EntityClassResolver($this->doctrine)
        );
        $field = 'e.category';
        $expr = $field;
        $parameterName = 'category_1';
        $value = 123;

        $qb = new QueryBuilder($this->em);
        $qb
            ->select('e')
            ->from(Entity\User::class, 'e')
            ->innerJoin('e.groups', 'groups');

        $expressionVisitor->setQuery($qb);
        $expressionVisitor->setQueryAliases(['e', 'groups']);
        $expressionVisitor->setQueryJoinMap(['groups' => 'groups']);

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        $expectedSubquery = 'SELECT category_subquery1'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Category category_subquery1'
            . ' INNER JOIN Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Category category_subquery1_criteria'
            . ' WITH category_subquery1_criteria = :category_1'
            . ' WHERE category_subquery1 MEMBER OF e.category'
            . ' AND ('
            . 'category_subquery1.rightField < category_subquery1_criteria.rightField'
            . ' AND category_subquery1.leftField > category_subquery1_criteria.leftField'
            . ' AND category_subquery1.rootField = category_subquery1_criteria.rootField)';

        self::assertEquals(
            new Expr\Func('EXISTS', [$expectedSubquery]),
            $result
        );
        self::assertEquals(
            [new Parameter($parameterName, $value)],
            $expressionVisitor->getParameters()
        );
    }

    public function testWalkComparisonExpressionWithoutRootField(): void
    {
        $this->expectGetConfiguration([
            'left'  => 'leftField',
            'right' => 'rightField'
        ]);
        $expression = new NestedTreeComparisonExpression(
            $this->treeListener,
            $this->doctrine
        );
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new FieldDqlExpressionProviderStub(),
            new EntityClassResolver($this->doctrine)
        );
        $field = '';
        $expr = $field;
        $parameterName = 'e_1';
        $value = 123;

        $qb = new QueryBuilder($this->em);
        $qb
            ->select('e')
            ->from(Entity\User::class, 'e')
            ->innerJoin('e.groups', 'groups');

        $expressionVisitor->setQuery($qb);
        $expressionVisitor->setQueryAliases(['e', 'groups']);
        $expressionVisitor->setQueryJoinMap(['groups' => 'groups']);

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        $expectedSubquery = 'SELECT e_subquery1'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\User e_subquery1'
            . ' INNER JOIN Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\User e_subquery1_criteria'
            . ' WITH e_subquery1_criteria = :e_1'
            . ' WHERE e_subquery1 = e'
            . ' AND ('
            . 'e_subquery1.rightField < e_subquery1_criteria.rightField'
            . ' AND e_subquery1.leftField > e_subquery1_criteria.leftField)';

        self::assertEquals(
            new Expr\Func('EXISTS', [$expectedSubquery]),
            $result
        );
        self::assertEquals(
            [new Parameter($parameterName, $value)],
            $expressionVisitor->getParameters()
        );
    }

    public function testWalkComparisonExpressionWhenIncludeRootIsTrue(): void
    {
        $this->expectGetConfiguration([
            'root'  => 'rootField',
            'left'  => 'leftField',
            'right' => 'rightField'
        ]);
        $expression = new NestedTreeComparisonExpression(
            $this->treeListener,
            $this->doctrine,
            true
        );
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new FieldDqlExpressionProviderStub(),
            new EntityClassResolver($this->doctrine)
        );
        $field = '';
        $expr = $field;
        $parameterName = 'e_1';
        $value = 123;

        $qb = new QueryBuilder($this->em);
        $qb
            ->select('e')
            ->from(Entity\User::class, 'e')
            ->innerJoin('e.groups', 'groups');

        $expressionVisitor->setQuery($qb);
        $expressionVisitor->setQueryAliases(['e', 'groups']);
        $expressionVisitor->setQueryJoinMap(['groups' => 'groups']);

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        $expectedSubquery = 'SELECT e_subquery1'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\User e_subquery1'
            . ' INNER JOIN Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\User e_subquery1_criteria'
            . ' WITH e_subquery1_criteria = :e_1'
            . ' WHERE e_subquery1 = e'
            . ' AND (('
            . 'e_subquery1.rightField < e_subquery1_criteria.rightField'
            . ' AND e_subquery1.leftField > e_subquery1_criteria.leftField'
            . ' AND e_subquery1.rootField = e_subquery1_criteria.rootField'
            . ') OR e_subquery1 = :e_1)';

        self::assertEquals(
            new Expr\Func('EXISTS', [$expectedSubquery]),
            $result
        );
        self::assertEquals(
            [new Parameter($parameterName, $value)],
            $expressionVisitor->getParameters()
        );
    }
}
