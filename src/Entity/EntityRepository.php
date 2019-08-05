<?php

namespace Nealis\EntityRepository\Entity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Nealis\EntityRepository\Data\Filter\Filter;
use Nealis\EntityRepository\Data\Filter\Rule;
use Nealis\EntityRepository\Entity\Field\Field;
use Nealis\Params\Params;
use Nealis\Result\Result;

abstract class EntityRepository implements EntityRepositoryInterface
{
    /** @var Connection */
    protected $connection;

    protected $defaultFilterRuleOperator = Rule::EQUALS;
    protected $allowEmptyFilters = true;

    /** @var  Entity */
    protected $entityClass;

    protected $defaultSorters = [];
    protected $identitySorters = [];

    protected $readStmt = "";
    protected $parameters = [];
    protected $filterParameters = [];

    protected $stmt = '';

    protected $createStmt = '';

    /**
     * @param $connection
     */
    public function __construct(Connection $connection)
    {
        $this->setConnection($connection);
        if (!empty($this->stmt) && empty($this->readStmt)) $this->setReadStmt($this->stmt);
    }

    /**
     * @return Entity
     * @throws \Exception
     */
    public function getEntityInstance()
    {
        $entityClass = $this->getEntityClass();

        if(empty($entityClass)) throw new \Exception('Undefined entityClass in EntityRepository');

        /** @var Entity $entity */
        $entity = new $entityClass($this);
        return $entity;
    }

    public function initIdentitySorters()
    {
        if (!empty($this->entityClass) && empty($this->identitySorters)) {
            $identities = $this->getEntityInstance()->getIdentity();

            foreach ($identities as $identity) {
                $this->identitySorters[$identity] = 'ASC';
            }
        }
    }

    /**
     * @param Params|array $params
     * @return Params
     */
    public function getQueryParams($params)
    {
        if (is_array($params)) {
            $params = new Params($params);
        }

        $filters = $params->get('filters', []);
        if (!$this->allowEmptyFilters) $filters = $this->removeEmptyFilters($filters);

        $params->init('sorters', []);

        $params->init('fixedFilters', []);
        $params->set('filters', array_merge($filters, $params->get('fixedFilters', [])));
        $params->init('parameters', []);

        $params->set('pageSize', $params->get('pageSize', 15, function($val){ return intval($val); }));
        $limit = $params->init('limit', $params->get('pageSize'));
        $page = $params->init('page', 1);
        $params->init('offset', intval(($page - 1) * $limit));

        $params->init('exportFields', []);

        return $params;
    }

    /**
     * @param Params|array $params
     * @return array
     */
    public function readAll($params = [])
    {
        //Retrocompatibilità, fino a questa modifica i parametri erano un array associativo
        if (is_array($params)) {
            $params = new Params($params);
        }

        $parameters = $params->get('parameters', []);
        $filters = $params->get('filters', []);
        $sorters = $this->getSorters($params->get('sorters', []));
        $limit = $params->get('limit', 0, function($val){ return intval($val); });
        $offset = $params->get('offset', 0);
        $page = $params->get('page', 1, function($val){ return intval($val); });
        $executeCount = $params->get('executeCount', 1);

        $readQuery = $this->getReadQuery();
        $readQuery = $this->prepareQuery(
            $readQuery,
            $parameters,
            $filters,
            $sorters,
            $limit,
            $offset
        );

        $data = $this->read($readQuery);
        if (empty($data) || intval($executeCount) === 0) {
            $count = 0;
        } else {
            $count = $this->readCount($params);
        }

        if($limit !== 0) {
            $totalPages = intval($count / $limit);
            if(($count % $limit) > 0) $totalPages += 1;
        } else {
            $totalPages = -1;
        }

        return [
            "count" => count($data),
            "totalRecords" => $count,
            "page" => $page,
            "totalPages" => $totalPages,
            "data" => $data,
            "offset" => $offset,
            "limit" => $limit,
        ];
    }

    /**
     * @param Params|array $params
     * @return array
     */
    public function readSelectData($params = [])
    {
        if (is_array($params)) {
            $params = new Params($params);
        }

        $distinctFields = implode(',', $params->get('selectParams.distinctFields', []));
        $parameters = $params->get('parameters', []);
        $filters = $params->get('filters', []);
        $sorters = $params->get('sorters', []);
        $limit = $params->get('limit', 0, function($val){ return intval($val); });
        $offset = $params->get('offset', 0);
        $page = $params->get('page', 1, function($val){ return intval($val); });

        $readQuery = $this->getReadQuery()
            ->select('DISTINCT ' . $distinctFields);

        $readQuery = $this->prepareQuery($readQuery, [], $filters, $sorters, $limit, $offset);
        $data = $this->read($readQuery);

        if (empty($data)) {
            $count = 0;
        } else {
            $this->getReadQuery()
                ->select('DISTINCT ' . $distinctFields)
                ->select('COUNT(1) as totcount');

            $countQuery = $this->getCountQuery();

            $countQuery = $this->prepareQuery(
                $countQuery,
                $parameters,
                $filters
            );

            $count = $this->count($countQuery);
        }

        if($limit !== 0) {
            $totalPages = intval($count / $limit);
            if(($count % $limit) > 0) $totalPages += 1;
        } else {
            $totalPages = -1;
        }

        return [
            "count" => count($data),
            "totalRecords" => $count,
            "page" => $page,
            "totalPages" => $totalPages,
            "data" => $data,
            "offset" => $offset,
            "limit" => $limit,
        ];
    }

    /**
     * @param array $sorters
     * @return array
     */
    public function getSorters($sorters = [])
    {
        $newSorters = empty($sorters) ? $this->defaultSorters : $sorters;

        foreach ($this->identitySorters as $key => $sorting) {
            if (!array_key_exists($key, $newSorters)) {
                $newSorters[$key] = $sorting;
            }
        }

        return $newSorters;
    }

    /**
     * @param Params|array $params
     * @return mixed
     */
    public function readCount($params = [])
    {
        if (is_array($params)) {
            $params = new Params($params);
        }

        $parameters = $params->get('parameters', []);
        $filters = $params->get('filters', []);

        $countQuery = $this->getCountQuery();

        $countQuery = $this->prepareQuery(
            $countQuery,
            $parameters,
            $filters
        );

        return $this->count($countQuery);
    }

    /**
     * @param $query
     * @param array $parameters
     * @param array $filters
     * @param array $sorters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function readQuery($query = null, $parameters = [], $filters = [], $sorters = [], $limit = 0, $offset = 0)
    {
        if($query === null) {
            $query = $this->getReadQuery();
        }
        $this->prepareQuery($query, $parameters, $filters, $sorters, $limit, $offset);
        $stmt = $query->execute();
        $data = $stmt->fetchAll();

        return $data;
    }

    /**
     * @param $filters
     */
    public function readBy($filters)
    {
        $query = $this->getReadQuery();

        $query = $this->prepareQuery($query, [], $filters);

        $data = $this->read($query);

        return $data;
    }

    public function readOneBy($filters)
    {
        $query = $this->getReadQuery();

        $query = $this->prepareQuery($query, [], $filters);
        $data = $this->readRow($query);

        return $data;
    }

    /**
     * @deprecated
     * @param $filters
     * @return array|false
     */
    public function readRowBy($filters)
    {
        return $this->readOneBy($filters);
    }

    public function find($query)
    {
        $data = $this->readRow($query);

        if(!$data) return false;

        return $this->create($data);
    }

    /**
     * @param array $keyData
     * @param bool $resolve
     * @return array|bool
     * @throws \Exception
     */
    public function findBy(array $keyData, $resolve = true)
    {
        if(empty($keyData)) throw new \Exception('No key data given');
        $filters = [];

        foreach($keyData as $fieldName => $value) {
            $filters[] = array(
                'field' => $fieldName,
                'op' => Rule::EQUALS,
                'data' => $value,
            );
        }

        $data = $this->readBy($filters);
        if(empty($data)) {
            return false;
        }

        $entities = [];
        foreach ($data as $row) {
            $entity = $this->create($row, $resolve, false, false);
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * @param array $keyData
     * @param bool $resolve
     * @return bool|Entity
     * @throws \Exception
     */
    public function findOneBy(array $keyData, $resolve = true)
    {
        if(empty($keyData)) throw new \Exception('No key data given');
        $filters = [];


        foreach($keyData as $fieldName => $value) {
            $filters[] = array(
                'field' => $fieldName,
                'op' => Rule::EQUALS,
                'data' => $value,
            );
        }

        $data = $this->readOneBy($filters);

        if(!$data) {
            return false;
        }

        $entity = $this->create($data, $resolve, false, false);
        return $entity;
    }

    /**
     * @param array $data
     * @param bool $resolve
     * @param bool $validate
     * @param bool $defaultOnEmpty
     * @param bool $convert
     * @return Entity
     * @throws \Exception
     */
    public function create($data = [], $resolve = true, $validate = false, $defaultOnEmpty = true, $convert = true)
    {
        $entity = $this->getEntityInstance();
        $entity->setData($data, $resolve, $validate, $defaultOnEmpty, $convert);
        return $entity;
    }

    /**
     * @param array $filters
     * @return array
     */
    protected function removeEmptyFilters($filters)
    {
        return array_filter($filters,
            function($value) {
                return ($value != '');
            }
        );
    }

    /**
     * @return Entity|null
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * @param mixed $entityClass
     */
    public function setEntityClass($entityClass)
    {
        $this->entityClass = $entityClass;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param $connection
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return string
     */
    public function getDefaultFilterRuleOperator()
    {
        return $this->defaultFilterRuleOperator;
    }

    /**
     * @param string $defaultFilterRuleOperator
     */
    public function setDefaultFilterRuleOperator($defaultFilterRuleOperator)
    {
        $this->defaultFilterRuleOperator = $defaultFilterRuleOperator;
    }

    /**
     * @param boolean $allowEmptyFilters
     */
    public function setAllowEmptyFilters($allowEmptyFilters)
    {
        $this->allowEmptyFilters = $allowEmptyFilters;
    }

    public function getTableName($withSchema = true)
    {
        $entityClass = $this->getEntityClass();
        return $entityClass::getTableName($withSchema);
    }

    /**
     * @param string $stmt
     * @return QueryBuilder
     * @throws \Exception
     */
    public function getReadQuery($stmt = null)
    {
        $this->initIdentitySorters();
        if ($stmt === null) $stmt = $this->getReadStmt();

        if (empty($stmt)) {
            $stmt = 'SELECT * FROM ' . $this->getTableName();

            if(empty($this->getTableName(false))) throw new \Exception('No stmt or Entity::tableName defined');
        }

        $query = $this->connection->createQueryBuilder();

        $query = $query->select('*')
            ->from(
                '('.$stmt.') ', 'res'
            );

        return $query;
    }

    /**
     * @param QueryBuilder $query
     * @return mixed
     */
    public function read($query = null)
    {
        if(is_null($query)) {
            $query = $this->getReadQuery();
            $this->prepareQuery($query);
        }
        $stmt = $query->execute();
        $data = $stmt->fetchAll();

        return $data;
    }

    /**
     * @param QueryBuilder $query
     * @return false | array
     */
    public function readRow($query = null)
    {
        if(is_null($query)) {
            $query = $this->getReadQuery();
            $query = $this->prepareQuery($query);
        }
        return $query->execute()->fetch();
    }

    /**
     * @param $countQuery
     * @return mixed
     */
    public function count($countQuery)
    {
        $data = $this->readRow($countQuery);
        $totCount = array_key_exists('totcount', $data) ? $data['totcount'] : $data['TOTCOUNT'];
        return intval($totCount);
    }

    /**
     * @param $query
     * @param array|Filter $filters
     * @param array $sorters
     * @param int $limit
     * @param int $offset
     * @param array $parameters
     * @return QueryBuilder
     */
    public function prepareQuery($query, $parameters = [], $filters = [], $sorters = [], $limit = 0, $offset = 0)
    {
        $query = $this->prepareFilters($query, $filters);
        $query = $this->prepareParameters($query, $parameters);
        $query = $this->prepareSorters($query, $sorters);
        $query = $this->prepareLimitOffset($query, intval($limit), intval($offset));

        return $query;
    }

    public function getFilterInstance()
    {
        return Filter::getInstance();
    }

    /**
     * @return string
     */
    public function getReadStmt()
    {
        $entityClass = $this->getEntityClass();

        $schemaName = '';
        if ($entityClass !== null) {
            $schemaName = $entityClass::$schemaName;
        }

        return str_replace('{{ SCHEMA }}', $schemaName , $this->readStmt);
    }

    /**
     * @param string $readStmt
     */
    public function setReadStmt($readStmt)
    {
        $this->readStmt = $readStmt;
    }

    /**
     * @return QueryBuilder
     */
    public function getCountQuery()
    {
        return $this->getReadQuery()
            ->select('COUNT(1) as totcount');
    }

    /**
     * @param QueryBuilder $query
     * @param $filters
     * @return QueryBuilder
     */
    public function prepareFilters(QueryBuilder $query, $filters)
    {
        if(!($filters instanceof Filter)) $filters = new Filter($filters, $this->defaultFilterRuleOperator);

        $where = $filters->toSQLWhere();

        $filterParameters = $filters->toSQLWhereVal();

        if(!empty($where)) {
            $query->andWhere($where);
        }
        else {
            $filterParameters = [];
        }

        $this->setFilterParameters($filterParameters);

        return $query;
    }

    /**
     * @param QueryBuilder $query
     * @param array $parameters
     * @return QueryBuilder
     */
    public function prepareParameters(QueryBuilder $query, $parameters = [])
    {
        $params = array_merge(array_values($this->getParameters()), array_values($parameters));
        $params = array_merge($params, $this->getFilterParameters());
        $query->setParameters($params);
        return $query;
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function addParameters($parameters)
    {
        $params = array_merge(array_values($this->getParameters()), array_values($parameters));
        $this->setParameters($params);

        return $this;
    }

    /**
     * @param QueryBuilder $query
     * @param array $sorters
     * @return QueryBuilder
     */
    public function prepareSorters($query, $sorters = [])
    {
        foreach ($sorters as $sort => $order) {
            $query->addOrderBy($sort, $order);
        }

        return $query;
    }

    /**
     * @param QueryBuilder $query
     * @param int $limit
     * @param int $offset
     * @return mixed
     */
    public function prepareLimitOffset($query, $limit = 0, $offset = 0)
    {
        if($limit > 0){
            $query->setMaxResults($limit);
        };

        if($offset > 0){
            $query->setFirstResult($offset);
        }

        return $query;
    }

    public function initDb($library = '')
    {
        $platform = $this->getDatabasePlatform();
        if($this->checkDb($library)) return false;

        /** @var Entity $entity */
        $entity = $this->getEntityInstance();

        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName($entity::$schemaName);
        $schema = new Schema([], [], $schemaConfig);
        $table = $schema->createTable($this->getTableName());

        /** @var Field $field */
        foreach($entity->getDbFields() as $field)
        {
            $name = $field->getColumnName();
            $type = $field->getColumnType();
            $options = [];

            if($field->isId() && $field->isGenerated()) $options['autoincrement'] = true;

            $nullable = $field->isNullable();
            if(!empty($nullable)) $options['notnull'] = false;

            if(!is_null($field->getDefault())) $options['default'] = $field->getDefault();

            $lenght = $field->getLength();
            if(!empty($lenght)) $options['length'] = $lenght;

            $table->addColumn($name, $type, $options);
        }

        $table->setPrimaryKey($entity->getIdentity());

        $queries = $schema->toSql($platform);

        foreach ($queries as $sql)
        {
            //Replace varchar into citext for case-insensitive string fields
            if ($platform instanceof PostgreSqlPlatform) {
                $sql = preg_replace('*VARCHAR\([0-9]+\)|VARCHAR/gi*', 'CITEXT', $sql);
            }
            $this->getConnection()->executeQuery($sql);
        }

        $this->initDbIndexes();
    }

    public function initDbIndexes()
    {
        $result = new Result();

        /** @var AbstractPlatform $databasePlatform */
        $databasePlatform = $this->getDatabasePlatform();
        $uniqueKeys = $this->getEntityInstance()->getUniqueKeys();

        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = $this->getConnection()->getSchemaManager();
        $tableName = $this->getTableName();

        foreach ($uniqueKeys as $uniqueKeysGroup) {
            $uniqueKeysGroupFields = $uniqueKeysGroup['fields'];
            $uniqueKeyColumns = array_column($uniqueKeysGroupFields, 'name');
            $tableNameWithoutSchema = $this->getTableName(false);
            $indexName = $tableNameWithoutSchema.'_uk_'.substr(sha1($tableNameWithoutSchema.'_uk_'.implode('', $uniqueKeyColumns)), 0, 6);

            /** @var Index $index */
            $storedIndexes = array_map(function($index) {
                return $index->getName();
            }, $schemaManager->listTableIndexes($tableName));

            if (!in_array($indexName, $storedIndexes)) {
                $index = new Index($indexName, $uniqueKeyColumns, true);
                $sql = $databasePlatform->getCreateIndexSQL($index, $tableName);
                $this->getConnection()->executeQuery($sql);
                $result->setData($indexName);
            } else {
                $result->addWarning(sprintf('Unique key %s already exists', $indexName));
            }
        }

        return $result;
    }

    public function checkDb($library = '')
    {
        $tableName = $this->getTableName();
        return $this->getConnection()->getSchemaManager()->tablesExist($tableName);
    }

    public function getDatabasePlatform()
    {
        $schemaManager = $this->connection->getSchemaManager();
        return $schemaManager->getDatabasePlatform();
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        if(!is_array($parameters)){
            $parameters = array($parameters);
        }
        $this->parameters = array_values($parameters);
    }

    /**
     * @return array
     */
    public function getFilterParameters()
    {
        return $this->filterParameters;
    }

    /**
     * @param array $filterParameters
     */
    public function setFilterParameters($filterParameters)
    {
        $this->filterParameters = $filterParameters;
    }

}
