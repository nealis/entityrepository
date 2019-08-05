<?php

namespace Nealis\EntityRepository\Entity\Field;

class Date extends Field
{
    protected $default = null;

    protected $format = 'Y-m-d';

    public function dataConvert($value)
    {
        if(is_string($value))
        {
            $value = \DateTime::createFromFormat($this->format, $value);
        }
        return $value;
    }

    public function dataUnConvert($value)
    {
        if(!is_null($value) && $value instanceof \DateTime)
        {
            $value = $value->format($this->format);
        }
        return $value;
    }
}
