<?php

namespace Nealis\EntityRepository\Entity\Field;

class Numeric extends Field
{
    protected $default = 0.0;

    protected $maxValue = null;
    protected $minValue = null;

    protected $precision = 11;

    protected $scale = 2;

    public function setDefault($value)
	{
        if($value !== null) $value = floatval($value);
        $this->default = $value;
    }

    public function dataConvert($value)
    {
        $value = $this->fixSeparators($value);
        $value = floatval($value);
        $value = $this->applyMinValue($value);
        $value = $this->applyMaxValue($value);
        $value = parent::dataConvert($value);

        return $value;
    }

    public function fixSeparators($value)
    {
        if($value !== null) {
            return str_replace(',', '.', $value.''); //TODO Remove replace after correct UI unFormat
        } else {
            return $value;
        }
    }

    public function dataUnConvert($value)
    {
        $value = parent::dataUnConvert($value);

        if(!is_null($value)) $value = floatval($value);

        return $value;
    }

    public function testIsEmpty($value)
    {
        if(is_null($value) || empty($value) || floatval($this->fixSeparators($value)) === 0.0) return true;
        return false;
    }

    public function applyMaxValue ($value)
    {
        if($this->hasMaxValue() && !is_null($value))
        {
            $maxV = $this->getMaxValue();

            $value = floatval($value);
            $maxV = floatval($maxV);

            if($value > $maxV)
            {
                $value = $maxV;
            }
        }

        return $value;
    }

    public function applyMinValue ($value)
    {
        if($this->hasMinValue() && !is_null($value))
        {
            $minV = $this->getMinValue();

            $value = floatval($value);
            $minV = floatval($minV);

            if($value < $minV)
            {
                $value = $minV;
            }
        }

        return $value;
    }

    //Getters & Setters

    /**
     * @return bool
     */
    public function hasMaxValue()
    {
        return !is_null($this->getMaxValue());
    }

    /**
     * @return bool
     */
    public function hasMinValue()
    {
        return !is_null($this->getMinValue());
    }

    /**
     * @description ritorna la maxlength
     * @returns {integer}
     */
    public function getMaxValue()
    {
        return $this->maxValue;
    }

    /**
     * @description imposta la maxlength
     * @param maxValue
     */
    public function setMaxValue($maxValue)
    {
        $this->maxValue = $maxValue;
        //$this->applyMaxValue();
    }

    /**
     * @description ritorna la minlength
     * @returns {integer}
     */
    public function getMinValue()
    {
        return $this->minValue;
    }

    /**
     * @description imposta la minlength
     * @param minValue
     */
    public function setMinValue($minValue)
    {
        $this->minValue = $minValue;
        //$this->applyMinValue();
    }

    /**
     * @return int
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * @param int $precision
     */
    public function setPrecision($precision)
    {
        $this->precision = $precision;
    }

    /**
     * @return int
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * @param int $scale
     */
    public function setScale($scale)
    {
        $this->scale = $scale;
    }
}
