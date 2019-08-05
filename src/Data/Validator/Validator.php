<?php

namespace Nealis\EntityRepository\Data\Validator;

use Nealis\EntityRepository\Entity\AbstractEntity;

class Validator
{
    /**
     * @description validator Name
     * @type string
     */
    protected $name = '';

    /**
     * @description next validator Name
     * @type string
     */
    protected $next;

    /**
     * @description messaggio di Errore
     * @type string
     */
    protected $errorMessages = ['error'];

    /**
     * @description messaggio di Errore
     * @type string
     */
    protected $errorDataTemplate = '';

    /**
     * @var array
     */
    protected $fieldsOptions = [];

    protected $enabled = true;

    public function __construct($config = [])
    {
        $reflection = new \ReflectionClass($this);

        foreach ($config as $key => $value) {
            $setMethod = 'set'.ucfirst($key);

            if ($reflection->hasMethod($setMethod)) {
                $this->$setMethod($value);
            } elseif ($reflection->hasProperty($key)) {
                $this->$key = $value;
            }
        }
    }

    //METHODS

    /**
     * @param AbstractEntity $entity
     * @return array
     */
    public function validate(AbstractEntity $entity)
    {
        return [];
    }

    //Spostato dal ForeignKeyValidator
    public function beforeRepositoryRead($entity) {}

    /**
     * @param int $index
     * @return mixed
     */
    public function getErrorMessage($index = 0)
    {
        return $this->errorMessages[$index];
    }

    /**
     * @param $fieldName
     * @return bool
     */
    public function hasField($fieldName)
    {
        foreach ($this->fieldsOptions as $f=>$field) {
            if ($field['name'] == $fieldName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getNext()
    {
        return $this->next;
    }

    /**
     * @param $next
     */
    public function setNext($next)
    {
        $this->next = $next;
    }

    /**
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled($enabled = true)
    {
        $this->enabled = $enabled;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->getEnabled();
    }

    /**
     * @param string $errorMessages
     */
    public function setErrorMessages($errorMessages)
    {
        $this->errorMessages = $errorMessages;
    }

    /**
     * @param $errorMessage
     * @param $data
     * @return mixed
     */
    protected function replaceErrorMessage($errorMessage, $data)
    {
        foreach ($data as $field => $value) {

            $fieldValue = sprintf("'%s'", $value);

            $errorMessage = str_replace(sprintf('{{%s}}', $field), $fieldValue, $errorMessage);
            $errorMessage = str_replace(sprintf('{{ %s }}', $field), $fieldValue, $errorMessage);
        }
        return $errorMessage;
    }

    public function getErrorDataTemplate($data = null, $entity = null)
    {
        return $this->errorDataTemplate;
    }
}
