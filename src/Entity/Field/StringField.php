<?php

namespace Nealis\EntityRepository\Entity\Field;

class StringField extends Field
{
    protected $default = '';

    /**
     * @description prefisso
     * @type string
     */
    protected $prefix;

    /**
     * @description suffisso
     * @type string
     */
    protected $suffix;

    /**
     * @description upperCase
     * @type string
     */
    protected $upperCase = false;

    /**
     * @description lowerCase
     * @type string
     */
    protected $lowerCase = false;

    /**
     * @description capitalCase
     * @type string
     */
    protected $capitalCase = false;

    /**
     * @description rightTrim
     * @type boolean
     */
    protected $lTrim = false;

    /**
     * @description rightTrim
     * @type boolean
     */
    protected $rTrim = true;

    public function dataConvert($value)
    {
        if(!is_null($value)) $value .= '';

        $value = $this->applyLTrim($value);
        $value = $this->applyRTrim($value);
        $value = $this->applyLowerCase($value);
        $value = $this->applyCapitalCase($value);
        $value = $this->applyUpperCase($value);

        $value = $this->applyPrefix($value);
        $value = $this->applySuffix($value);

        $value = parent::dataConvert($value);

        return $value;
    }

    public function dataUnConvert($value)
    {
        $value = $this->removePrefix($value);
        $value = $this->removeSuffix($value);

        $value = parent::dataUnConvert($value);

        return $value;
    }

    /**
     * @description set Default Value
     * @param value
     */
    public function setDefault($value = null)
    {
        if ($value === null)
            $this->default = null;
        else
            $this->default = '' . $value;
    }

    /**
     * @param $value
     * @return bool
     */
    public function testIsEmpty($value)
    {
        if ($value === null || trim($value.'') === '')
            return true;
        else
            return false;
    }

    /**
     * @param $value
     * @return string
     */
    public function applyPrefix($value)
    {
        if ($value !== null) {
            if ($this->suffix !== null) {

                if (mb_substr($value, 0, mb_strlen($this->prefix)) !== $this->prefix) {
                    $value = $this->prefix . $value;
                }
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function applySuffix($value)
    {
        if ($value !== null) {
            if ($this->suffix !== null) {

                if (mb_substr($value, mb_strlen($value) - mb_strlen($this->suffix), mb_strlen($this->suffix) != $this->suffix)) {
                    $value = $value . $this->suffix;
                }
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function removePrefix($value)
    {
        if ($value !== null) {
            if ($this->prefix !== null) {
                if (mb_substr($value, 0, mb_strlen($this->prefix)) == $this->prefix) {
                    $value = mb_substr($value, mb_strlen($this->prefix), mb_strlen($value) - mb_strlen($this->prefix));
                }

            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function removeSuffix($value)
    {
        if ($value !== null) {

            if ($this->suffix !== null) {

                if (mb_substr($value, mb_strlen($value) - mb_strlen($this->suffix), mb_strlen($this->suffix)) == $this->suffix) {
                    $value = mb_substr($value, 0, mb_strlen($value) - mb_strlen($this->suffix));
                }
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function applyUpperCase($value)
    {
        if ($value !== null) {
            if ($this->upperCase) {
                $value = mb_strtoupper($value);
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function applyLowerCase($value)
    {
        if ($value !== null) {
            if ($this->lowerCase) {
                $value = mb_strtolower($value);
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function applyCapitalCase($value)
    {
        if ($value !== null) {
            if ($this->capitalCase) {
                $value = ucfirst($value);
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function applyLTrim($value)
    {
        if ($value !== null) {
            if ($this->lTrim) {
                $value = ltrim($value);
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return string
     * @throws \Exception
     */
    public function applyRTrim($value)
    {
        if ($value !== null) {
            //Modifica aggiunta per non far bloccare l'applicazione
            if ($this->rTrim) {
                if (is_string($value)) {
                    $value = rtrim($value);
                } else {
                    throw new \Exception(sprintf("Attention: field %s has wrong type, must correct the model", $this->getName()));
                }
            }
        }

        return $value;
    }


    //Getter & Setters

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * @param string $suffix
     */
    public function setSuffix($suffix)
    {
        $this->suffix = $suffix;
    }

    /**
     * @return string
     */
    public function getUpperCase()
    {
        return $this->upperCase;
    }

    /**
     * @param string $upperCase
     */
    public function setUpperCase($upperCase)
    {
        $this->upperCase = $upperCase;
        if($upperCase) {
            $this->lowerCase = false;
            $this->capitalCase = false;
        }
    }

    /**
     * @return string
     */
    public function getLowerCase()
    {
        return $this->lowerCase;
    }

    /**
     * @param string $lowerCase
     */
    public function setLowerCase($lowerCase)
    {
        $this->lowerCase = $lowerCase;
        if($lowerCase) {
            $this->upperCase = false;
            $this->capitalCase = false;
        }
    }

    /**
     * @return string
     */
    public function getCapitalCase()
    {
        return $this->capitalCase;
    }

    /**
     * @param string $capitalCase
     */
    public function setCapitalCase($capitalCase)
    {
        $this->capitalCase = $capitalCase;
        if($capitalCase) {
            $this->upperCase = false;
            $this->lowerCaseCase = false;
        }
    }

    /**
     * @return boolean
     */
    public function isLTrim()
    {
        return $this->lTrim;
    }

    /**
     * @param boolean $lTrim
     */
    public function setLTrim($lTrim)
    {
        $this->lTrim = $lTrim;
    }

    /**
     * @return boolean
     */
    public function isRTrim()
    {
        return $this->rTrim;
    }

    /**
     * @param boolean $rTrim
     */
    public function setRTrim($rTrim)
    {
        $this->rTrim = $rTrim;
    }
}
