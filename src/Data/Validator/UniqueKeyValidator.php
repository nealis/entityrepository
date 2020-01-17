<?php

namespace Nealis\EntityRepository\Data\Validator;

use Nealis\EntityRepository\Data\Filter\Rule;
use Nealis\EntityRepository\Data\Validator\Validator as EntityValidator;
use Nealis\EntityRepository\Entity\Entity;

class UniqueKeyValidator extends EntityValidator
{
    protected $errorMessages = [];

    protected $repository;

    protected $uniqueKeys;

    public function __construct(array $uniqueKeys, $repository)
    {
        $options = [
            'uniqueKeys' => $uniqueKeys,
            'repository' => $repository,
        ];
        parent::__construct($options);

        $this->errorMessages = $this->getDefaultErrorMessages();
        $this->initDefaults();
    }

    public function initDefaults()
    {
        foreach ($this->uniqueKeys as &$uniqueKeysGroup) {
            $uniqueKeysGroupFields = &$uniqueKeysGroup['fields'];

            foreach ($uniqueKeysGroupFields as &$uniqueKey) {
                if (!array_key_exists('ignoreEmpty', $uniqueKey)) {
                    $uniqueKey['ignoreEmpty'] = false;
                }
            }
        }
    }

    public function validate($entity)
    {
        $errors = [];

        if(!$this->isKeyEmpty($entity)) {
            $isNew = $entity->isEmptyStoredIdentityData();
            foreach ($this->uniqueKeys as $uniqueKeysGroupKey => $uniqueKeysGroupValue ) {
                $entityRepository = $entity->getEntityRepository();
                $filters = $entityRepository->getFilterInstance();
                $identity = $entity->getIdentity();
                //Exclude self identity value while editing mode
                if (!$isNew) {
                    foreach ($identity as $id) {
                        $filters->addRule($id, Rule::NOT, [$entity->get($id)]);
                    }
                }

                if (!array_key_exists('fields', $uniqueKeysGroupValue)) {
                    throw new \Exception('fields attribute not found');
                }

                $uniqueKeysGroupFields = $uniqueKeysGroupValue['fields'];
                foreach ($uniqueKeysGroupFields as $uniqueKeyField) {
                    $fieldName = $uniqueKeyField['name'];
                    $fieldIgnoreEmpty = $uniqueKeyField['ignoreEmpty'];
                    if (!$fieldIgnoreEmpty || ($entity->notEmpty($fieldName))) {
                        $filters->addRule($fieldName, Rule::EQUALS, [$entity->get($fieldName)]);
                    }
                }

                $this->beforeRepositoryRead($entity);
                $query = $this->repository->getReadQuery();
                $query = $this->repository->prepareQuery($query, [], $filters);
                $result = $this->repository->readRow($query);
                if ($result) {
                    $errorMessageDescription = $this->replaceErrorMessage($this->getErrorMessage($uniqueKeysGroupKey), $entity->getData());
                    $errors[] = $errorMessageDescription;
                }
            }
        }

        return $errors;
    }

    public function hasField($fieldName)
    {
        foreach ($this->uniqueKeys as $uniqueKeysGroup) {
            $uniqueKeysGroupFields = $uniqueKeysGroup['fields'];
            foreach ($uniqueKeysGroupFields as $uniqueKeyField) {
                if ($uniqueKeyField['name'] == $fieldName) {
                    return true;
                }
            }
        }
        return false;
    }

    public function isKeyEmpty($entity)
    {
        foreach ($this->uniqueKeys as $uniqueKeysGroup) {
            $uniqueKeysGroupFields = $uniqueKeysGroup['fields'];
            foreach ($uniqueKeysGroupFields as $uniqueKeyField) {
                $fieldName = $uniqueKeyField['name'];
                $field = $entity->getField($fieldName);

                if ($field === null) {
                    throw new \Exception(sprintf('key field %s not found', $fieldName));
                }

                if ($entity->notEmpty($fieldName)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function getErrorDataTemplate($fieldGroupName = null, $entity = null)
    {
        $errorDataTemplate = '';

        foreach ($fieldGroupName as $fieldName) {
            if ($entity instanceof Entity) {
                $field = $entity->getField($fieldName);
                $label = $field->getLabel();
            } else {
                $label = $fieldName;
            }

            $errorDataTemplate .= $label . ' = {{ ' . $fieldName . ' }} ';
        }

        return $errorDataTemplate;
    }

    protected function getDefaultErrorMessage()
    {
        return "TO BE IMPLEMENTED";
    }

    protected function getDefaultErrorMessages($entity = null)
    {
        $errorMessages = [];

        foreach ($this->uniqueKeys as $uniqueKeysGroupKey => $uniqueKeysGroupValues){
//            $uniqueKeysGroupFields = $this->getUniqueKeysGroupFields($uniqueKeysGroupValues['fields']);
            $errorMessages[$uniqueKeysGroupKey] = $this->getDefaultErrorMessage();
        }

        return $errorMessages;
    }

    protected function getUniqueKeysGroupFields(array $uniqueKeysGroupFields)
    {
        $fields = [];

        foreach ($uniqueKeysGroupFields as $field) {
            array_push($fields, $field['name']);
        }

        return $fields;
    }

    public function updateErrorMessages($entity = null)
    {
        $this->errorMessages = $this->getDefaultErrorMessages($entity);
    }
}
