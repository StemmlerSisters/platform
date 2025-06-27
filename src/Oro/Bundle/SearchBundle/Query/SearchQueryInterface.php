<?php

namespace Oro\Bundle\SearchBundle\Query;

use Doctrine\Common\Collections\Expr\Expression;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Oro\Bundle\SearchBundle\Query\Result\Item;

/**
 * Represents a search query.
 */
interface SearchQueryInterface
{
    /**
     * Returning the wrapped Query object. Should be used only at storage level.
     *
     * @return Query
     */
    public function getQuery();

    /**
     * Execute the query() and return postprocessed data.
     *
     * @return Item[]
     */
    public function execute();

    /**
     * Returning unprocessed result.
     *
     * @return Result
     */
    public function getResult();

    /**
     * Return number of records of search query without limit parameters
     *
     * @return int
     */
    public function getTotalCount();

    /**
     * Adding a field to be selected from the Search Index database system.
     *
     * @param string|array $fieldName
     * @param null $enforcedFieldType
     * @return SearchQueryInterface
     */
    public function addSelect($fieldName, $enforcedFieldType = null);

    /**
     * Returns the columns that are being selected from the DB.
     * Ignores aliases information.
     *
     * @return array
     */
    public function getSelect();

    /**
     * Returning the aliases found in the select expressions.
     * When adding a select field using addSelect(), a special SQL
     * syntax is supported for renaming fields. This method returns
     * the alias=>original field association array.
     * Note that it won't return fields without aliases set.
     *
     * @return array
     */
    public function getSelectAliases();

    /**
     * Returns the data fields that are returned in the results.
     * Fields can contain type prefixes. Aliases are respected.
     * Result is a combination of getSelect() and getSelectAliases().
     *
     * @return array
     */
    public function getSelectDataFields();

    /**
     * Returning the WHERE clause parts. Should be used only for internal purposes.
     *
     * @return Criteria
     */
    public function getCriteria();

    /**
     * Gets FROM part.
     *
     * @return string[]|string|null
     */
    public function getFrom();

    /**
     * Sets FROM part.
     *
     * @param string[]|string $entities
     * @return SearchQueryInterface
     */
    public function setFrom($entities);

    /**
     * Adding an expression to WHERE.
     *
     * @param Expression  $expression
     * @param null|string $type
     * @return SearchQueryInterface
     */
    public function addWhere(Expression $expression, $type = AbstractSearchQuery::WHERE_AND);

    /**
     * Set order by
     *
     * @param string $fieldName
     * @param string $direction
     * @param string $type
     *
     * @return SearchQueryInterface
     */
    public function setOrderBy($fieldName, $direction = Query::ORDER_ASC, $type = Query::TYPE_TEXT);

    public function addOrderBy(
        string $fieldName,
        string $direction = Query::ORDER_ASC,
        string $type = Query::TYPE_TEXT,
        bool $prepend = false
    ): SearchQueryInterface;

    /**
     * Get order by field
     *
     * @return string
     */
    public function getSortBy();

    /**
     * Getting the sort order, i.e. "ASC" etc.
     *
     * @return string
     */
    public function getSortOrder();

    /**
     * Set first result offset
     *
     * @param int $firstResult
     *
     * @return SearchQueryInterface
     */
    public function setFirstResult($firstResult);

    /**
     * Get first result offset
     *
     * @return int
     */
    public function getFirstResult();

    /**
     * Set max results
     *
     * @param int $maxResults
     *
     * @return SearchQueryInterface
     */
    public function setMaxResults($maxResults);

    /**
     * Get limit parameter
     *
     * @return int
     */
    public function getMaxResults();

    /**
     * Add aggregating operation to a search query
     *
     * @param string $name Name of the aggregating
     * @param string $field Fields that should be used to perform aggregating
     * @param string $function Applied aggregating function
     * @param array $parameters Additional aggregation parameters
     * @return SearchQueryInterface
     */
    public function addAggregate($name, $field, $function, array $parameters = []);

    /**
     * Return list of all applied aggregating operations
     *
     * @return array ['<name>' => ['field' => <field>, 'function' => '<function>', 'parameters' => <params>]]
     */
    public function getAggregations();

    /**
     * Sets a query hint. If the hint name is not recognized, it is silently ignored.
     *
     * @param string $name  The name of the hint.
     * @param mixed  $value The value of the hint.
     *
     * @return $this
     */
    public function setHint(string $name, $value): self;

    /**
     * Gets the value of a query hint. If the hint name is not recognized, FALSE is returned.
     *
     * @param string $name The name of the hint.
     *
     * @return mixed The value of the hint or FALSE, if the hint name is not recognized.
     */
    public function getHint(string $name);

    /**
     * Check if the query has a hint
     *
     * @param string $name The name of the hint
     *
     * @return bool False if the query does not have any hint
     */
    public function hasHint(string $name): bool;

    /**
     * Return the key value map of query hints that are currently set.
     *
     * @return array<string,mixed>
     */
    public function getHints(): array;
}
