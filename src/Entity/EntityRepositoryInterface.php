<?php

namespace Nealis\EntityRepository\Entity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

interface EntityRepositoryInterface
{
    public function getReadQuery();

    public function getCountQuery();

    /**
     * @param QueryBuilder $query
     * @param $filters
     */
    public function prepareFilters(QueryBuilder $query, $filters);

    /**
     * @param QueryBuilder $query
     * @param array $parameters
     * @return QueryBuilder
     */
    public function prepareParameters(QueryBuilder $query, $parameters=array());

    /**
     * @param array $parameters
     */
    public function addParameters($parameters);

    /**
     * @param QueryBuilder $query
     * @param array $sorters
     */
    public function prepareSorters($query, $sorters=array());

    /**
     * @param QueryBuilder $query
     * @param int $limit
     * @param int $offset
     * @return mixed
     */
    public function prepareLimitOffset($query, $limit=0, $offset=0);

    /**
     * @param QueryBuilder $query
     * @param array|Filter $filters
     * @param array $sorters
     * @param int $limit
     * @param int $offset
     * @param array $parameters
     * @return QueryBuilder
     */
    public function prepareQuery($query, $parameters=array(), $filters = array(), $sorters=array(), $limit=0, $offset=0);

    /**
     * @param QueryBuilder $query
     * @return mixed
     */
    public function read($query);

    /**
     * @param QueryBuilder $query
     * @return mixed
     */
    public function readRow($query);

    /**
     * @param QueryBuilder $query
     * @return mixed
     */
    public function readCount($query);

    /**
     * @return Connection
     */
    public function getConnection();

    /**
     * @param Connection $connection
     */
    public function setConnection(Connection $connection);

    /**
     * @return string
     */
    public function getReadStmt();

    /**
     * @param string $readStmt
     */
    public function setReadStmt($readStmt);

    /**
     * @return array
     */
    public function getParameters();

    /**
     * @param array $parameters
     */
    public function setParameters($parameters);

    /**
     * @return array
     */
    public function getFilterParameters();

    /**
     * @param array $filterParameters
     */
    public function setFilterParameters($filterParameters);
}
