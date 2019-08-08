<?php

namespace Nealis\EntityRepository\Entity;

use HTMLPurifier;
use HTMLPurifier_Config;
use Nealis\EntityRepository\Data\Filter\Rule;
use Nealis\EntityRepository\Data\Filter\Filter;
use Nealis\Result\Result;
use Nealis\EntityRepository\Data\Validator\FieldNotBlankValidator;
use Nealis\EntityRepository\Data\Validator\UniqueKeyValidator;
use Nealis\EntityRepository\Data\Validator\Validator as EntityValidator;
use Nealis\EntityRepository\Entity\Field\Field;

class Entity implements \ArrayAccess
{

    public static $schemaName = '';
    public static $tableName = null;

    /**
     * @var Field[]
     */
    protected $fields;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * Variable that stores original identityData to insert/update/delete/
     * @var array
     */
    protected $storedIdentityData = [];

    /**
     * @var EntityRepository
     */
    protected $entityRepository;

    /**
     * @var array
     */
    protected $validators  = [];

    //TODO implement associations
    //TODO implement resolvers

    protected $storedData = false;

    protected $uniqueKeys = [];

    protected $currentUser;

    protected $sanitize = true;

    public function __construct(EntityRepositoryInterface $entityRepository = null)
    {
        if ($entityRepository !== null) {
            $this->entityRepository = $entityRepository;
        }

        $this->resetEntityConfiguration();
    }

    public function initFields(){}

    protected function _initFields()
    {
        $fields = $this->fields;
        $this->fields = [];

        foreach ($fields as $fieldName => $fieldOptions) {

            //Persist true for defined fields
            if(!array_key_exists('persist', $fieldOptions)) {
                $fieldOptions['persist'] = true;
            }

            //Add fieldNotBlankValidator to field validators list
            if (array_key_exists('required', $fieldOptions) && $fieldOptions['required']) {
                $validators = [];
                $fieldOptionsValidators = isset($fieldOptions['validators']) ? $fieldOptions['validators'] : [];
                $fieldNotBlankValidator = $this->getFieldNotBlankValidatorInstance($fieldName);
                $validators['required'] = $fieldNotBlankValidator;

                $fieldOptions['validators'] = array_merge($validators, $fieldOptionsValidators);
            }

            $this->fields[$fieldName] = $this->getField($fieldName, $fieldOptions);
        }
    }

    /**
     * @return UniqueKeyValidator
     */
    protected function getUniqueKeysValidatorInstance()
    {
        $uniqueKeyValidator = new UniqueKeyValidator($this->getUniqueKeys(), $this->getEntityRepository());
        $uniqueKeyValidator->updateErrorMessages($this);
        return $uniqueKeyValidator;
    }

    protected function _initUniqueKeys()
    {
        $this->validators[] = $this->getUniqueKeysValidatorInstance();
    }

    /**
     * @param $fieldName
     * @return FieldNotBlankValidator
     */
    protected function getFieldNotBlankValidatorInstance($fieldName)
    {
        return new FieldNotBlankValidator($fieldName);
    }

    /**
     * @param $fieldName
     * @param array $fieldOptions
     * @return Field
     * @throws \Exception
     */
    public function getField($fieldName, $fieldOptions = [])
    {
        if (array_key_exists($fieldName, $this->fields)) {
            $field = $this->fields[$fieldName];
        } else {
            if (!array_key_exists('name', $fieldOptions)) $fieldOptions['name'] = $fieldName;
            if (!array_key_exists('persist', $fieldOptions)) $fieldOptions['persist'] = false; //Persist false for undefined fields
            $field = Field::getInstance($fieldOptions);
        }

        return $field;
    }

    /**
     * Get all values. Getter methods will be called on the values.
     *
     * @param bool $persistData
     * @param bool $unConvert
     * @param bool $defaultOnEmpty
     * @return array
     */
    public function getData($persistData = false, $unConvert = true, $defaultOnEmpty = false)
    {
        $data = [];

        if ($persistData)
        {
            $rawData = $this->getRawData();

            foreach ($this->getFields() as $fieldName => $field)  {
                if ($field->isPersist()) {
                    if(array_key_exists($fieldName, $rawData)) {
                        $data[$fieldName] = $this->get($fieldName, $unConvert, $defaultOnEmpty);
                    } else if (!array_key_exists($fieldName, $rawData) && !$field->isNullable()) {
                        $data[$fieldName] = $this->get($fieldName, $unConvert, true);
                    }
                }
            }
        }
        else
        {
            foreach ($this->getRawData() as $fieldName => $value) {
                $data[$fieldName] = $this->get($fieldName, $unConvert, $defaultOnEmpty);
            }
        }

        return $data;
    }

    /**
     * @param array $data
     * @param bool $resolve
     * @param bool $validate
     * @param bool $defaultOnEmpty
     * @param bool $convert
     * @return $this
     */
    public function setData(array $data = [], $resolve = true, $validate = true, $defaultOnEmpty = true, $convert = true)
    {
        if ($this->storedData === false) {
            $this->storedData = $data;
        }

        foreach ($this->getOrderedData($data) as $fieldName => $value) {
            $this->set($fieldName, $value, $resolve, $validate, $defaultOnEmpty, $convert);
        }

        //Se esiste almeno una chiave identity nel parametro data valorizzo lo storedIdentityData
        if (!empty(array_intersect_key($data, array_flip($this->getIdentity())))) {
            $this->setStoredIdentityData($this->getIdentityData($data));
        }

        return $this;
    }

    /**
     * @param null $fieldName
     * @param bool $resolve
     * @param bool $validate
     * @param bool $convert
     * @param bool $defaultOnEmpty
     * @return $this
     */
    public function reset($fieldName = null, $resolve = true, $validate = false, $defaultOnEmpty = true, $convert = true)
    {
        if (!is_null($fieldName)) {
            $this->set($fieldName, null, $resolve, $validate, $defaultOnEmpty, $convert);
        } else {
            //Reset is triggered for all fields that have values or that can't be null (identity fields are excluded)
            foreach($this->getEditFields() as $fieldName => $field) {
                $this->reset($fieldName, $resolve, $validate, $defaultOnEmpty, $convert);
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getEditFields()
    {
        $editFields = [];
        $rawFields = array_keys($this->getRawData());
        foreach($this->getFields() as $fieldName => $field)
        {
            if (!$field->isId() && (in_array($fieldName, $rawFields) || !$field->isNullable())) {
                $editFields[$fieldName] = $field;
            }
        }

        return $editFields;
    }

    /**
     * @return Field[]
     */
    public function getDbFields()
    {
        $dbFields = [];
        foreach ($this->getFields() as $fieldName => $field) {
            if ($field->isPersist()) {
                $dbFields[$fieldName] = $field;
            }
        }

        return $dbFields;
    }

    public function getFieldConfig($fieldName, $searchByColumnName = false)
    {
        //$searchByColumnName => determina se cercare per Field Name o Column Name
        if (!$searchByColumnName) {
            if (array_key_exists($fieldName, $this->fields)) {
                return $this->fields[$fieldName]->toArray();
            } else {
                throw new \Exception(
                    sprintf('Field %s not found for getFieldConfig', $fieldName)
                );
            }
        } else {
            foreach ($this->fields as $fieldConfig) {
                $fieldConfig = $fieldConfig->toArray();

                if ($fieldConfig['columnName'] == $fieldName) return $fieldConfig;
            }

            throw new \Exception(
                sprintf('Field with Column Name %s not found for getFieldConfig', $fieldName)
            );
        }
    }

    public function getFieldsConfig()
    {
        $fieldsConfig = [];
        foreach ($this->getFields() as $field) {
            $fieldsConfig[] = $field->toArray();
        }

        return $fieldsConfig;
    }

    public function getUniqueKeyAssociativeFilters()
    {
        $uniqueKeyFilters = [];

        foreach ($this->uniqueKeys as $uniqueKeyGroup) {
            $uniqueKeyFilter = [];
            foreach ($uniqueKeyGroup as $uniqueKey) {
                $fieldName = $uniqueKey['name'];
                $uniqueKeyFilter[$fieldName] = $this->get($fieldName);
            }
            $uniqueKeyFilters[] = $uniqueKeyFilter;
        }

        return $uniqueKeyFilters;
    }

    public function getUniqueKeyFilters()
    {
        $filters = $this->getUniqueKeyAssociativeFilters();
        foreach ($filters as $filter) {
            $filters[] = new Filter($filter);
        }

        return $filters;
    }

    public function get($fieldName, $convert = true, $defaultOnEmpty = false)
    {
        $value = $this->getRaw($fieldName);
        $field = $this->getField($fieldName);

        if ($defaultOnEmpty && $this->testIsEmpty($fieldName, $value)) {
            $value = $field->getDefault();
        }

        if ($convert && !($value === null && $field->isId())) {
            //TODO convert event
            $value = $field->dataUnConvert($value);
        }

        return $value;
    }

    public function notEmpty($fieldName)
    {
        return !$this->isEmpty($fieldName);
    }

    public function isEmpty($fieldName)
    {
        return $this->testIsEmpty($fieldName, $this->get($fieldName));
    }

    public function testIsEmpty($fieldName, $value)
    {
        $field = $this->getField($fieldName);
        return $field->testIsEmpty($value);
    }

    /**
     * @param string $fieldName
     * @param mixed $value
     * @param bool $resolve
     * @param bool $validate
     * @param bool $defaultOnEmpty
     * @param bool $convert
     * @return array|mixed|\Symfony\Component\Validator\ConstraintViolationListInterface
     */
    public function set($fieldName, $value = null, $resolve = true, $validate = true, $defaultOnEmpty = true, $convert = true)
    {
        $field = $this->getField($fieldName);

        //TODO beforeSetvalue event
        $value = $field->beforeSetValue($fieldName, $value, $this);

        if (!$field->isId() && ((is_null($value) && !$field->isNullable()) || ($defaultOnEmpty && $this->testIsEmpty($fieldName, $value)))) {
            $value = $field->getDefault();
        }
        if ($convert && !($value === null && $field->isId())) {
            //TODO convert event
            $value = $field->dataConvert($value);
        }

        $this->setRaw($fieldName, $value);

        $errors = $validate ? $this->validate($fieldName) : [];

        //TODO resolve
        if ($resolve) $this->resolve($fieldName);

        //TODO afterSetValue event
        $field->afterSetValue($fieldName, $value, $this);

        return $errors;
    }

    public function resolve($fieldName)
    {
        $field = $this->getField($fieldName);
        $field->resolve($fieldName, $this);
    }

    public function getConstraints($fieldName = null, $withKey = true)
    {
        $constraints = [];

        if (!empty($fieldName)){
            $field = $this->getField($fieldName);
            $fieldConstraints = $field->getConstraints();
            if (!empty($fieldConstraints)) {
                if ($withKey) $constraints[$fieldName] = $fieldConstraints;
                else $constraints = $fieldConstraints;
            }
        }
        else
        {
            foreach ($this->getFields() as $fieldName=>$field) {
                $fieldConstraints = $this->getConstraints($fieldName, false);
                if (!empty($fieldConstraints)) $constraints[$fieldName] = $fieldConstraints;
            }
        }

        return $constraints;
    }

    public function getIdentity($onlyGenerated = false)
    {
        $identity = [];
        foreach ($this->getFields() as $fieldName=>$field)
        {
            if ($field->isId()) {
                if($onlyGenerated) {
                    if ($field->isGenerated())
                        $identity[] = $fieldName;
                } else {
                    $identity[] = $fieldName;
                }
            }
        }

        return $identity;
    }

    /**
     * @param $data
     * @return array
     */
    public function getOrderedData($data)
    {
        $orderedData = [];

        foreach ($this->getFieldsNames() as $fieldName) {
            if (array_key_exists($fieldName, $data)) {
                $orderedData[$fieldName] = $data[$fieldName];
                unset($data[$fieldName]);
            }
        }

        return array_merge($orderedData, $data);
    }

    /**
     * @return array
     */
    public function getFieldsNames()
    {
        return array_map(function($field) {
            return $field['name'];
        }, $this->getFieldsConfig());
    }

    public function getIdentityColumnNames($onlyGenerated = false) {
        $identity = $this->getIdentity($onlyGenerated);
        return array_map(function ($fieldName) {
            $field = $this->getFieldConfig($fieldName);
            return $field['columnName'];
        }, $identity);
    }

    public function getIdentitySequenceName() {
        $columns = $this->getIdentityColumnNames(true);
        return sprintf('%s_%s_seq', static::getTableName(), implode('_', $columns));
    }

    public function getColumnTypes($data = null)
    {
        $types = [];

        if (is_null($data)) {
            foreach($this->getFields() as $fieldName => $field) {
                $types[] = $field->getColumnType();
            }
        } else {
            foreach($data as $fieldName => $value) {
                $field = $this->getField($fieldName);
                $types[] = $field->getColumnType();
            }
        }
        return $types;
    }

    public function exists($identityData)
    {
        $result = $this->readData($identityData);
        return $result ? true : false;
    }

    protected function beforeSave(){}
    protected function afterSave(){}

    /**
     * Save this entity to the database, either with an insert or
     * update query.
     */
    public function save()
    {
        //TODO events
        $this->beforeSave();
        $result = $this->isEmptyStoredIdentityData() ? $this->insert() : $this->update();
        //TODO events
        $this->afterSave();

        return $result;
    }

    /**
     * @return Result
     */
    public function insert()
    {
        $errors = $this->validate();
        $result = false;

        if (empty($errors))
        {
            try {
                $result = $this->_insert();
            }
            catch (\Exception $e)
            {
                $errors = [
                    '_insert' => $e->getMessage()
                ];
            }
        }

        return new Result([
            'result' => $result,
            'errors' => $errors,
            'data' => $this->getData(),
            'successTitle' => 'Operation completed successfully',
            'warningTitle' => 'Operation completed with warnings',
            'errorTitle' => 'Operation terminated with errors!',
        ]);
    }

    /**
     * Persist this entity to the database using an insert query.
     * @param bool $triggerEvents
     * @return mixed
     * @throws \Exception
     */
    protected function _insert($triggerEvents = true) {
        //TODO events
        if ($triggerEvents) $this->beforeInsert();

        $data = $this->getData(true, false);
        $identityData = $this->getIdentityData($data);

        if ($this->exists($identityData)) {
            throw new \Exception("You may not insert an already stored entity");
        }

        $data = $this->unsetIdentityData($data);
        //TODO Non serve, viene già fatto getData con primo parametro true, giusto?
        $persistData = $this->getPersistData($data);

        if ($this->sanitize) {
            $persistData = $this->sanitizeRow($persistData);
        }

        $result = $this->executeInsert($persistData, $data);

        $this->refresh();

        //TODO events
        if ($triggerEvents) $this->afterInsert();

        return $result;
    }

    /**
     * @param $data
     * @return array
     */
    protected function getPersistData($data)
    {
        $persistData = [];

        foreach ($data as $fieldName => $fieldValue)  {
            $field = $this->getField($fieldName);

            if ($field->isPersist()) {
                $dataFieldName = $field->getColumnName();

                $persistData[$dataFieldName] = $fieldValue;
            }
        }

        return $persistData;
    }

    protected function beforeInsert(){}
    protected function afterInsert(){}

    /**
     * @param bool $refresh
     * @return Result
     */
    public function update($refresh = true)
    {
        $errors = $this->validate();
        $result = false;

        if (empty($errors))
        {
            try {
                $result = $this->_update($refresh);
            }
            catch (\Exception $e)
            {
                $errors = [
                    '_update' => $e->getMessage()
                ];
            }
        }

        return new Result([
            'success' => $result,
            'errors' => $errors,
            'data' => $this->getData(),
            'successTitle' => 'Operation completed successfully',
            'warningTitle' => 'Operation completed with warnings',
            'errorTitle' => 'Operation terminated with errors!',
        ]);
    }

    /**
     * Update this entity in the database.
     * @param bool $refresh
     * @param bool $triggerEvents
     * @return int|void
     * @throws \Exception
     */
    protected function _update($refresh = true, $triggerEvents = true)
    {
        //TODO events
        if ($triggerEvents) $this->beforeUpdate();

        $storedIdentityData = $this->getStoredIdentityData();

        if (!$this->exists($storedIdentityData)) {
            throw new \Exception('Entity doesn\'t exist');
        }
        $data = $this->getData(true, false);
        $identityData = $this->getIdentityData($data);

        if ($storedIdentityData != $identityData){ //if changing identity key
            if ($this->exists($identityData)) {
                throw new \Exception("You may not update identity key with an already existing ");
            }
        }

        $data = $this->unsetIdentityData($data);
        $persistData = $this->getPersistData($data);
        $persistData = $this->sanitizeRow($persistData);

        $result = $this->executeUpdate($persistData, $data);

        if ($refresh) {
            $this->refresh();
        }

        //TODO events
        if ($triggerEvents) $this->afterUpdate();

        return $result;
    }

    protected function beforeUpdate(){}
    protected function afterUpdate(){}

    /**
     * @param bool $validate
     * @return Result
     */
    public function delete($validate = true)
    {
        $errors = $validate ? $this->validateDelete() : null;
        $result = false;

        if (empty($errors)) {
            try {
                $result = $this->_delete();
            } catch (\Exception $e) {
                $errors = [
                    '_delete' => $e->getMessage()
                ];
            }
        }

        return new Result([
            'result' => $result,
            'errors' => $errors,
            'successTitle' => 'Operation completed successfully',
            'warningTitle' => 'Operation completed with warnings',
            'errorTitle' => 'Operation terminated with errors!',
        ]);
    }

    /**
     * @param bool|true $triggerEvents
     * @return int
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     * @throws \Exception
     */
    protected function _delete($triggerEvents = true)
    {
        //TODO events
        if ($triggerEvents) $this->beforeDelete();

        $where = $this->getStoredIdentityData();

        $result = $this->executeDelete($where);

        if ($result < 1) throw new \Exception('No record to delete');

        //TODO events
        if ($triggerEvents) $this->afterDelete();

        return $result;
    }

    protected function beforeDelete(){}
    protected function afterDelete(){}

    /**
     * @param bool $resolve
     * @return Entity
     * @throws \Exception
     */
    public function refresh($resolve = true) {
        $identityData = $this->getIdentityData();
        $data = $this->readData($identityData);

        if (!$data) throw new \Exception('Failed to refresh data, entity not found');
        return $this->setData($data, $resolve, false);
    }

    protected function readData($identityData)
    {
        if (empty($identityData)) throw new \Exception('Empty identity data');

        $entityRepository = $this->getEntityRepository();
        if (!is_null($entityRepository)) {
            $entityRepository->setDefaultFilterRuleOperator(Rule::EQUALS);
            $query = $entityRepository->getReadQuery();
            $query = $entityRepository->prepareQuery($query, [], $identityData);
            $result = $entityRepository->readRow($query);
        } else {
            $result = $this->readDataWithoutEntityRepository($identityData);
        }

        return $result;
    }

    protected function getIdentityData($data = null)
    {
        $identityData = [];

        foreach($this->getIdentity() as $idName) {
            if ($data) {
                $identityData[$idName] = null;
                if (array_key_exists($idName, $data)) {
                    $identityData[$idName] = $data[$idName];
                }
            } else {
                $identityData[$idName] = $this->get($idName);
            }
        }

        return $identityData;
    }

    protected function setIdentityData($data)
    {
        if (!is_array($data)) $data = [$data];

        $identityData = [];
        foreach ($this->getIdentity() as $key => $idName) {
            $value = $data[$key];
            $this->set($idName, $value);
            $identityData[$idName] = $value;
        }

        $this->setStoredIdentityData($identityData);
    }

    public function unsetIdentityData($data)
    {
        foreach($this->getIdentity(true) as $idName) {
            unset($data[$idName]);
        }

        return $data;
    }

    //Validations

    /**
     * @return bool
     */
    public function isValid()
    {
        $errors = $this->validate();
        return empty($errors);
    }

    public function validate($fieldName = null)
    {
        //ESC Fields Validators
        if (empty($errors)) {
            $errors = $this->validateFields($fieldName);
        }

        //ESC Validators
        if (empty($errors)) {
            $errors = $this->validateRow($fieldName);
        }

        return $errors;
    }

    public function validateFields($fieldName)
    {
        $errors = [];

        $fields = empty($fieldName) ? $this->getFields() : [$this->getField($fieldName)];

        foreach ($fields as $field) {
            $fieldValidators = $field->getValidators();

            $fieldErrors = [];
            foreach($fieldValidators as $index => $validator) {
                $fieldError = $this->validateFieldData($validator, $field->getName());
                if (!empty($fieldError)) {
                    $fieldErrors = array_merge($fieldErrors, $fieldError);
                }
            }

            if (!empty($fieldErrors)) {
                if(empty($fieldName)) {
                    $errors[$field->getName()] = $fieldErrors;
                } else {
                    $errors = array_merge($errors, $fieldErrors);
                }
            }

            $this->enableValidators($fieldValidators);
        }

        return $errors;
    }

    public function validateRow($fieldName)
    {
        $errors = [];
        $rowValidators = $this->getValidators();

        foreach($rowValidators as $index => $rowValidator) {
            if (empty($fieldName) || ($rowValidator instanceof EntityValidator && $rowValidator->hasField($fieldName))) {
                $rowErrors = $this->validateFieldData($rowValidator, $fieldName);
                if (!empty($rowErrors)) {
                    $errors = array_merge($errors, $rowErrors);
                }
            }
        }

        $this->enableValidators($rowValidators);

        return $errors;
    }

    function validateFieldData($validator, $fieldName) {

        $errors = [];
        if ($validator instanceof EntityValidator)
        {
            /**
             * @var EntityValidator $validator
             */
            if ($validator->isEnabled()) {
                $validator->setEnabled(false);
                $errors = $validator->validate($this);
                if (!empty($errors)) $this->setRecursiveValidatorEnabled($validator, false, true);
            }
        } else if (is_callable($validator)) {
            $errors = call_user_func($validator, $this, $fieldName);
        } else {
            throw new \Exception('Validator type not valid');
        }

        return $errors;
    }

    /**
     * @param EntityValidator $rowValidator
     * @param $value
     * @param bool $ignoreCurrent
     */
    public function setRecursiveValidatorEnabled($rowValidator, $value, $ignoreCurrent = true)
    {
        if (!$rowValidator instanceof EntityValidator) {
            return;
        }

        if (!$ignoreCurrent) {
            $rowValidator->setEnabled($value);
        }

        $nextValidator = array_key_exists($rowValidator->getNext(), $this->validators) ? $this->validators[$rowValidator->getNext()] : null;

        if ($nextValidator !== null) {
            $this->setRecursiveValidatorEnabled($nextValidator, $value, false);
        }
    }

    /**
     * @param $validators
     */
    public function enableValidators($validators)
    {
        foreach($validators as $index=>$rowValidator) {
            if ($rowValidator instanceof EntityValidator) {
                $rowValidator->setEnabled(true);
            }
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isDeleteValid()
    {
        $errors = $this->validateDelete();
        return empty($errors);
    }

    /**
     * @return array
     */
    public function validateDelete()
    {
        return [];
    }

    /**
     * Get all values. Getter methods will not be called on the
     * values.
     *
     * @return array The values
     */
    public function getRawData()
    {
        return $this->data;
    }

    /**
     * @param array $values
     * @return $this
     */
    public function setRawData($values = [])
    {
        foreach ($values as $field  => $value) {
            $this->setRaw($field , $value);
        }
        return $this;
    }

    /**
     * @param $field
     */
    protected function unsetRaw($field)
    {
        unset($this->data[$field]);
    }

    final protected function getRaw($field)
    {
        if (!isset($this->data[$field])) {
            return null;
        }
        return $this->data[$field];
    }

    protected function setRaw($field, $value)
    {
        $this->data[$field] = $value;
    }

    final public function __get($field)
    {
        $methodName = 'get' . $field;

        return (method_exists($this, $methodName)) ? $this->$methodName() : $this->get($field);
    }

    final public function __set($field, $value)
    {
        $methodName = 'set' . $field;

        if (\method_exists($this, $methodName)) {
            $this->$methodName($value);
        } else {
            $this->set($field, $value);
        }
    }

    final public function __isset($field)
    {
        return isset($this->data[$field]);
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    final public function offsetExists($field)
    {
        return $this->__isset($field);
    }

    final public function offsetGet($field)
    {
        return $this->get($field);
    }

    final public function offsetSet($field, $value)
    {
        $this->set($field, $value);
    }

    final public function offsetUnset($field)
    {
        $this->__unset($field);
    }

    public function __toString()
    {
        return json_encode($this->data);
    }

    /**
     * @return array
     */
    public function getStoredIdentityData()
    {
        return $this->storedIdentityData;
    }

    /**
     * @param array $storedIdentityData
     */
    public function setStoredIdentityData($storedIdentityData)
    {
        $this->storedIdentityData = $storedIdentityData;
    }

    /**
     * @return bool
     */
    public function isEmptyStoredIdentityData()
    {
        $storedIdentityData = $this->getStoredIdentityData();
        $emptyStoredIdentityData = true;

        foreach($storedIdentityData as $key => $value) {
            if (!$this->isEmptyIdentityValue($value)) {
                $emptyStoredIdentityData = false;
                break;
            }
        }

        return $emptyStoredIdentityData;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function isEmptyIdentityValue($value)
    {
        return empty($value);
    }

    /**
     * @return Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param mixed $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @param $withSchema
     * @return null|string
     */
    public static function getTableName($withSchema = true)
    {
        if ($withSchema && !empty(static::$schemaName)) {
            return static::$schemaName . '.' . static::$tableName;
        } else {
            return static::$tableName;
        }
    }

    /**
     * @return EntityRepository
     */
    public function getEntityRepository()
    {
        return $this->entityRepository;
    }

    /**
     * @param AbstractEntityRepository $entityRepository
     */
    public function setEntityRepository($entityRepository)
    {
        $this->entityRepository = $entityRepository;
    }

    /**
     * @return array
     */
    public function getValidators()
    {
        return $this->validators;
    }

    /**
     * @param array $validators
     */
    public function setValidators($validators)
    {
        $this->validators = $validators;
    }

    /**
     * @return array
     */
    public function getUniqueKeys()
    {
        //Retrocompatibilità per gestione di uniqueKeys che non hanno l'attributo fields
        $uniqueKeys = array_map(
            function($uniqueKeysGroup) {
                if (!array_key_exists('fields', $uniqueKeysGroup)) {
                    return [
                        'fields' => $uniqueKeysGroup
                    ];
                }
                return $uniqueKeysGroup;
            }, $this->uniqueKeys
        );

        return $uniqueKeys;
    }

    protected function resetEntityConfiguration()
    {
        $this->fields = [];
        $this->validators = [];
        $this->initFields();
        $this->_initFields();
        $this->_initUniqueKeys();
    }

    /** Utility Methods to set insert/update info about user/time */

    protected function getCurrentTime()
    {
        return new \DateTime();
    }

    protected function getCurrentUser()
    {
        return $this->currentUser;
    }

    public function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
    }

    public function getInsertDefaultValues()
    {
        return [
            'insert_user' => $this->getCurrentUser(),
            'insert_time' => $this->getCurrentTime(),
        ];
    }

    public function getUpdateDefaultValues()
    {
        return [
            'update_user' => $this->getCurrentUser(),
            'update_time' => $this->getCurrentTime(),
        ];
    }

    /**
     * @return bool|array
     */
    public function getStoredData()
    {
        return $this->storedData;
    }

    public function sanitizeRow($row)
    {
        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);

        $parsedRow = [];
        foreach ($row as $field => $value) {
            $parsedRow[$field] = $this->getSanitizedValue($field, $value, $purifier);
        }
        return $parsedRow;
    }

    public function getSanitizedValue($field, $value, HTMLPurifier $purifier)
    {
        $fieldConfig = $this->getFieldConfig($field, true);
        if ($fieldConfig['sanitize']) {
            return is_string($value) ? $purifier->purify($value) : $value;
        } return $parsedRow[$field] = $value;
    }

    public function setSanitize($sanitize)
    {
        $this->sanitize = $sanitize;
    }

    protected function executeInsert($persistData, $data)
    {
        $types = $this->getColumnTypes($data);
        $insertResult = $this->entityRepository->getConnection()->insert(static::getTableName(), $persistData, $types);
        $this->updateLastInsertedIdentityData();
        return $insertResult;
    }

    protected function executeUpdate($persistData, $data)
    {
        $types = $this->getColumnTypes($data);
        $where = $this->getStoredIdentityData();
        $updateResult = $this->entityRepository->getConnection()->update(static::getTableName(), $persistData, $where, $types);
        return $updateResult;
    }

    protected function executeDelete($where)
    {
        $deleteResult = $this->entityRepository->getConnection()->delete(static::getTableName(), $where);
        return $deleteResult;
    }


    protected function readDataWithoutEntityRepository($identityData)
    {
        throw new \Exception('No Entity Repository found!');
    }

    protected function updateLastInsertedIdentityData()
    {
        $id = $this->entityRepository->getConnection()->lastInsertId($this->getIdentitySequenceName());
        if (!empty($id)) {
            $this->setIdentityData($id);
        }
    }

    public function translate($string)
    {
        //TODO valutare se e come utilizzare il translator
        return $string;
    }
}
