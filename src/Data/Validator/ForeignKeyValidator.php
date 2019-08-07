<?php

namespace Nealis\EntityRepository\Data\Validator;

use Nealis\EntityRepository\Data\Filter\Filter;
use Nealis\EntityRepository\Data\Filter\Rule;
use Nealis\EntityRepository\Data\Validator\Validator as EntityValidator;
use Nealis\EntityRepository\Entity\Entity;
use Nealis\EntityRepository\Entity\EntityRepository;

class ForeignKeyValidator extends EntityValidator
{
    /**
     * @description array di configurazioni dei fields da validare
     * @example
     * <code>
     * [
     * {
     * 	name : 'Cd_Cliente',
     *  fkName : 'CDCLIENTE',
     *  op : Ojq.conf.data.filter.rule.operation.EQUALS,
     *  onlyParam : true //il campo non viene trattato come parametro, ma solo come valore aggiuntivo nei filtri
     *  ignoreEmpty : true //il campo viene incluso nel filtro solo se non è vuoto
     * },
     * ...]
     * </code>
     */
    protected $fkFieldsOptions = [];

    /**
     * @description messaggio di Errore
     * @type string
     */
    protected $errorMessages = [];

    /**
     * @var EntityRepository
     */
    protected $repository;

    /**
     * ForeignKeyValidator constructor.
     * @param array $fkFieldsOptions
     * @param $repository
     */
    public function __construct($fkFieldsOptions, $repository, $errorMessages = [], $errorDataTemplate = '')
    {
        $options = [
            'fkFieldsOptions' => $fkFieldsOptions,
            'repository' => $repository,
        ];
        if(!empty($errorMessages)) $options['errorMessages'] = $errorMessages;
        if(!empty($errorDataTemplate)){
            $options['errorDataTemplate'] = $errorDataTemplate;
        } else {
            $this->setErrorMessages(['Foreign key %s not found']);
        }
        parent::__construct($options);

        foreach ($this->fkFieldsOptions as $f=>&$fkField) {
            if (!array_key_exists("onlyParam", $fkField)) {
                $fkField['onlyParam'] = false;
            }
            if (!array_key_exists("ignoreEmpty", $fkField)) {
                $fkField['ignoreEmpty'] = false;
            }
            if (!array_key_exists("op", $fkField)) {
                $fkField['op'] = Rule::EQUALS;
            }
        }
    }

    /**
     * @param Entity $entity
     * @return array
     * @throws \Exception
     */
    public function validate($entity)
    {
        $errors = [];

        $isKeyEmpty = $this->isKeyEmpty($entity);

        if (!$isKeyEmpty) {
            $filters = new Filter();

            foreach ($this->fkFieldsOptions as $f=>$fkField) {
                if ($fkField['op'] === null) {
                    $fkField['op'] = Rule::EQUALS;
                }
                $fieldName = $fkField['name'];

                if (!$fkField['ignoreEmpty'] || ($entity->notEmpty($fieldName))) {
                    $fieldValue = $entity->get($fieldName);

                    $tempOp = $fkField['op'];
                    if ($fieldValue === null) {
                        $tempOp = Rule::ISNULL;
                    }

                    $filters->addRule($fkField['fkName'], $tempOp, $fieldValue);
                }
            }

            $this->beforeRepositoryRead($entity);

            $query = $this->repository->getReadQuery();
            $query = $this->repository->prepareQuery($query, [], $filters);
            $result = $this->repository->readRow($query);

            if (!$result) {
                $errorMessage = sprintf($this->getErrorMessage(), $this->getErrorDataTemplate(null, $entity));
                $errorMessage = $this->replaceErrorMessage($errorMessage, $entity->getData());

                foreach ($this->fkFieldsOptions as $f=>$fkField) {
                    $fieldName = $fkField['name'];

                    if (!$fkField['onlyParam'] && (!$fkField['ignoreEmpty'] || $entity->notEmpty($fieldName))) {
                        $errors[$fieldName] = $errorMessage;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param $fieldName
     * @return bool
     */
    public function hasField($fieldName)
    {
        foreach ($this->fkFieldsOptions as $f=>$fkField) {
            if ($fkField['name'] == $fieldName && !$fkField['onlyParam']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws \Exception
     */
    public function isKeyEmpty($entity)
    {
        foreach ($this->fkFieldsOptions as $f=>$fkField) {
            $fieldName = $fkField['name'];

            if (!$fkField['onlyParam']) {
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

    /**
     * @param null $data
     * @param Entity $entity
     * @return string
     * @throws \Exception
     */
    public function getErrorDataTemplate($data = null ,$entity = null)
    {
        $names = "";

        foreach ($this->fkFieldsOptions as $f=>$fkField) {
            $fieldName = $fkField['name'];

            if (!empty($names)) {
                $names .= ", ";
            }

            if ($fkField['onlyParam'] != true) {
                $label = $entity->getField($fieldName)->getLabel();
                $names .= $label;
            }
        }

        return $names;
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function setRepository($repository)
    {
        $this->repository = $repository;
        return $this;
    }
}
