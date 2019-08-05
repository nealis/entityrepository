<?php

namespace Nealis\EntityRepository\Entity\Field;

class BoolField extends Field
{
    protected $default = 0;

    public function testIsEmpty($value)
    {
        return ! filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
