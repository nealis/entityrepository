<?php

namespace Nealis\EntityRepository\Entity\Field;

abstract class Configurable
{
    public function __construct($config=array())
    {
        $this->initConfig($config);
    }

    protected function initConfig($config = array())
    {
        foreach($config as $confName=>$conf)
        {
            $this->$confName = $conf;
        }
    }

    protected function has($name)
    {
        return $this->hasMethod($name) || $this->hasProperty($name);
    }

    protected function hasProperty($propertyName)
    {
        return property_exists($this, $propertyName);
    }

    protected function hasMethod($method)
    {
        return method_exists($this, $method);
    }

    protected function callMethod($methodName, $paramArray=null)
    {
        if(!is_null($paramArray))
            return call_user_func_array(array($this, $methodName), $paramArray);
        else
            return call_user_func(array($this, $methodName));
    }

    protected function callPropertyMethod($methodName, $paramArray=null)
    {
        if($paramArray !== null)
            return call_user_func_array($this->$methodName, $paramArray);
        else
            return call_user_func($this->$methodName);
    }

    protected function evaluate($name, $paramArray=null)
    {
        if($this->hasProperty($name))
        {
            if(is_callable($this->$name))
            {
                array_push($paramArray, $this);
                return $this->callPropertyMethod($name, $paramArray);
            }
            else
            {
                return $this->$name;
            }
        }
        else if($this->hasMethod($name))
        {
            return $this->callMethod($name, $paramArray);
        }
        else
        {
            throw new \Exception("Class Property/Method $name not found!");
        }
    }

    public function __call($method, $args)
    {

        if($this->has($method))
        {
            return $this->evaluate($method, $args);
        }
        else
        {
            $method = '_'.$method;
            return $this->evaluate($method, $args);
        }

    }
}
