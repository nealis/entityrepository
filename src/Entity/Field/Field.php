<?php

namespace Nealis\EntityRepository\Entity\Field;

use Nealis\EntityRepository\Data\Validator\FieldNotBlankValidator;

class Field extends Configurable
{
    const TYPE_STRING = "string";
    const TYPE_INTEGER = "integer";
    const TYPE_NUMERIC = "numeric";
    const TYPE_DECIMAL = "decimal";
    const TYPE_FLOAT = "float";
    const TYPE_BOOL = "bool";
    const TYPE_DATE = "date";
    const TYPE_TIME = "time";
    const TYPE_DATETIME = "datetime";
    const TYPE_OBJECTID = "objectid";

    /**
     * @var bool
     */
    protected $id = false;

    /**
     * @var bool
     */
    protected $generated = false;

    /**
     * @var string
     */
    protected $type = '';

    /**
     * @var bool
     */
    protected $nullable = true;

    /**
     * @name default
     * @type mixed
     * @description default value
     */
    protected $default = null;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $columnName = '';

    /**
     * @var string
     */
    protected $label = '';

    /**
     * @var string
     */
    protected $columnType = '';

    /**
     * @var integer
     * @description indica la lunghezza massima del dato, i caratteri successivi vengono rimossi
     */
    protected $length = null;

    /**
     * @var bool
     */
    protected $persist = true;

    /**
     * @var bool
     */
    protected $required = false;

    /**
     * @var array
     */
    protected $constraints = [];

    /**
     * @var array
     */
    protected $validators = [];

    /**
     * @description indica la regular expression dei SINGOLI caratteri accettati come dati del field
     * @type string
     * @default null
     */
    protected $regExp = null;

    /**
     * @description indica la presenza in un'esportazione
     * @type bool
     * @default true
     */
    protected $export = true;

    /**
     * @description indica la formattazione excel del campo
     * @type string
     * @default true
     */
    protected $excelFormat = 'GENERAL';

    /**
     * @description indica se sanitizzare il valore tramite HTMLPurifier
     * @type bool
     * @default true
     */
    protected $sanitize = true;

    //METHODS

    protected function initConfig($config = [])
    {
        parent::initConfig($config);

        $fieldName = array_key_exists('name', $config) ? $config['name'] : '';
        $type = array_key_exists('type', $config) ? $config['type'] : static::TYPE_STRING;

        if (empty($this->columnName)) {
            $this->columnName = $fieldName;
        }
        if (empty($this->label)) {
            $this->label = $fieldName;
        }
        if (empty($this->columnType)) {
            $this->columnType = $type;
        }
    }

    /**
     * @param $value
     * @return bool
     */
    public function testIsEmpty($value)
    {
        if ($value === null || empty($value)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $value
     * @description converts the value to be stored
     * @return mixed|string
     */
    public function dataConvert($value)
    {
        $value = $this->applyRegExp($value);
        $value = $this->applyMaxLength($value);

        $value = $this->convert($value);

        return $value;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function _convert($value)
    {
        return $value;
    }

    /**
     * @param $value
     * @description converts the value from the storage for being used
     * @return mixed
     */
    public function dataUnConvert ($value)
    {
        $value = $this->unConvert($value);

        return $value;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function _unConvert($value)
    {
        return $value;
    }

    /**
     * @param $value
     * @return mixed|string
     */
    public function applyRegExp($value)
    {
        if (!empty($this->regExp) && $value !== null) {
            $value = $this->replaceRegExp($value, $this->regExp);
        }

        return $value;
    }

    /**
     * @param $value
     * @param $regExp
     * @return mixed|string
     */
    protected function replaceRegExp($value, $regExp)
    {
        $value = ''.$value;
        $newvalue = $value;
        for ($i=0; $i<mb_strlen($value); $i++) {

            $c = $value[$i];
            if (! preg_match($regExp, $c))
                $newvalue = str_replace($c, '', $newvalue);
        }

        return $newvalue;
    }

    /**
     * @param $fieldName
     * @param $value
     * @param $entity
     * @return mixed
     */
    public function _beforeSetValue($fieldName, $value, $entity)
    {
        return $value;
    }

    /**
     * @param $fieldName
     * @param $value
     * @param $entity
     * @return mixed
     */
    public function _afterSetValue($fieldName, $value, $entity)
    {
        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function applyMaxLength($value)
    {
        if ($this->hasMaxLength() && $value !== null) {
            $maxL = $this->getMaxLength();
            $value = ''.$value;
            if (mb_strlen($value) > $maxL) {
                $value = mb_substr($value, 0, $maxL);
            }
        }

        return $value;
    }

    /**
     * @return bool
     */
    public function hasMaxLength()
    {
        $maxL = $this->getMaxLength();

        if (!empty($maxL) && $maxL > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array $config
     * @return mixed
     * @throws \Exception
     */
    public static function getInstance($config = [])
    {
        if (array_key_exists('generated', $config) && $config['generated']) $config['default'] = null; //Default null for generated fields

        $type = array_key_exists('type', $config) ? $config['type'] : 'string';

        switch ($type) {
            case static::TYPE_STRING:
                $className = 'StringField';
                break;
            case static::TYPE_DECIMAL:
                $className = 'Numeric';
                break;
            case static::TYPE_FLOAT:
                $className = 'Numeric';
                break;
            case static::TYPE_NUMERIC:
                $className = 'Numeric';
                break;
            case static::TYPE_INTEGER:
                $className = 'Integer';
                break;
            case static::TYPE_DATE:
                $className = 'Date';
                break;
            case static::TYPE_DATETIME:
                $className = 'Date';
                break;
            case static::TYPE_TIME:
                $className = 'Date';
                break;
            case static::TYPE_BOOL:
                $className = 'BoolField';
                break;
            case static::TYPE_OBJECTID:
                $className = 'ObjectIdField';
                break;
            default:
                throw new \Exception(sprintf('Field type %s not implemented', $type));
        }

        $namespace = 'Nealis\\EntityRepository\\Entity\\Field\\';
        $fieldClassName = $namespace.$className;

        return new $fieldClassName($config);
    }

    /**
     * @param $fieldName
     * @param $entity
     */
    public function _resolve($fieldName, $entity) {}

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->isId(),
            'generated' => $this->isGenerated(),
            'required' => $this->isRequired(),
            'type' => $this->getType(),
            'nullable' => $this->isNullable(),
            'default' => $this->getDefault(),
            'name' => $this->getName(),
            'columnName' => $this->getColumnName(),
            'label' => $this->getLabel(),
            'columnType' => $this->getColumnType(),
            'length' => $this->getLength(),
            'persist' => $this->isPersist(),
            'constraints' => $this->getConstraints(),
            'validators' => $this->getValidators(),
            'regExp' => $this->getRegExp(),
            'export' => $this->isExport(),
            'excelFormat' => $this->getExcelFormat(),
            'sanitize' => $this->isSanitize(),
        ];
    }

    //Getters & Setters

    /**
     * @return boolean
     */
    public function isId()
    {
        return $this->id;
    }

    /**
     * @param boolean $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return boolean
     */
    public function isGenerated()
    {
        return $this->generated;
    }

    /**
     * @param boolean $generated
     */
    public function setGenerated($generated)
    {
        $this->generated = $generated;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return bool
     */
    public function isNullable()
    {
        return $this->nullable;
    }

    /**
     * @param mixed $nullable
     */
    public function setNullable($nullable)
    {
        $this->nullable = $nullable;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default)
    {
        $this->default = $default;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getColumnName()
    {
        return $this->columnName;
    }

    /**
     * @param string $columnName
     */
    public function setColumnName($columnName)
    {
        $this->columnName = $columnName;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getColumnType()
    {
        return $this->columnType;
    }

    /**
     * @param string $columnType
     */
    public function setColumnType($columnType)
    {
        $this->columnType = $columnType;
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @param int $length
     */
    public function setLength($length)
    {
        $this->length = $length;
    }

    /**
     * @return boolean
     */
    public function isPersist()
    {
        return $this->persist;
    }

    /**
     * @param boolean $persist
     */
    public function setPersist($persist)
    {
        $this->persist = $persist;
    }

    /**
     * @return array
     */
    public function getConstraints()
    {
        return $this->constraints;
    }

    /**
     * @param array $constraints
     */
    public function setConstraints($constraints)
    {
        $this->constraints = $constraints;
    }

    /**
     * @return string $regExp
     */
    public function getRegExp()
    {
        return $this->regExp;
    }

    /**
     * @param string $regExp
     */
    public function setRegExp($regExp)
    {
        $this->regExp = $regExp;
    }

    public function getMaxLength()
    {
        return $this->getLength();
    }

    /**
     * @return boolean
     */
    public function isExport()
    {
        return $this->export;
    }

    /**
     * @param boolean $export
     */
    public function setExport($export)
    {
        $this->export = $export;
    }

    /**
     * @return mixed
     */
    public function getValidators()
    {
        return $this->validators;
    }

    /**
     * @param mixed $validators
     */
    public function setValidators($validators)
    {
        $this->validators = $validators;
    }

    /**
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * @param boolean $required
     * @param string $requiredMessage
     */
    public function setRequired($required, $requiredMessage = null)
    {
        $this->required = $required;
        $validators = $this->getValidators();
        if ($required) {
            $validators['required'] = new FieldNotBlankValidator($this->getName());
            if (null !== $requiredMessage) {
                $validators['required']->setErrorMessages($requiredMessage);
            }
        } else {
            foreach ($validators as $key => $validator) {
                if ($validator instanceof FieldNotBlankValidator) {
                    unset($validators[$key]);
                }
            }
        }
        $this->setValidators($validators);
    }

    /**
     * @param bool $excelFormat
     */
    public function setExcelFormat($excelFormat)
    {
        $this->excelFormat = $excelFormat;
    }

    /**
     * @return bool
     */
    public function getExcelFormat()
    {
        return $this->excelFormat;
    }

    /**
     * @return bool
     */
    public function isSanitize()
    {
        return $this->sanitize;
    }
}
