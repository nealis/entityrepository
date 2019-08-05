<?php

namespace Nealis\EntityRepository\Data;

use Exception;

class KeyException extends \Exception
{
    protected $key;

    // Redefine the exception so message isn't optional
    public function __construct($key, $message, $code = 0, Exception $previous = null) {

        $this->key = $key;

        parent::__construct($message, $code, $previous);
    }

    public function getKey()
    {
        return $this->key;
    }
}
