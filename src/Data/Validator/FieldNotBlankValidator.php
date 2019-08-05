<?php

namespace Nealis\EntityRepository\Data\Validator;


use Nealis\Doctrine\Entity\Entity;

class FieldNotBlankValidator extends Validator
{
    /** @var  string */
    protected $fieldName;

    /**
     * @var string
     */
    protected $errorMessages = ['%s is mandatory'];

    /**
     * FieldNotBlankValidator constructor.
     * @param string $fieldName
     */
    public function __construct(string $fieldName)
    {
        parent::__construct();
        $this->fieldName = $fieldName;
    }

    /**
     * @param Entity $entity
     * @return array
     * @throws \Exception
     */
    public function validate(Entity $entity)
    {
        $errors = [];

        $fieldValue = $entity->get($this->fieldName);

        $hasValue = (is_string($fieldValue) && strlen($fieldValue) > 0) || (!is_string($fieldValue) && !empty($fieldValue));

        if (!$hasValue) {
            $errors[] = sprintf($this->getErrorMessage(), $entity->getField($this->fieldName)->getLabel());
        }

        return $errors;
    }

    /**
     * @param $fieldName
     * @return bool
     */
    public function hasField($fieldName)
    {
        if ($this->fieldName == $fieldName) {
            return true;
        }

        return false;
    }
}
