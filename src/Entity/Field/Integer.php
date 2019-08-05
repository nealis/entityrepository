<?php

namespace Nealis\EntityRepository\Entity\Field;

class Integer extends Numeric
{
    protected $default = 0;

    public function dataConvert($value)
    {
        if(!is_null($value)) $value = intval($value);

        $value = parent::dataConvert($value);

        if(!is_null($value)) $value = intval($value);

        return $value;
    }

    public function dataUnConvert($value)
    {
        $value = parent::dataUnConvert($value);

        if(!is_null($value)) $value = intval($value);

        return $value;
    }
}
