<?php
namespace AliasCompiler;

use Closure;

class ValueCompiler
{

    private static $instance;

    public static function getInstance(): ValueCompiler
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }



    protected $compilers = [];

    protected function __construct(){
        if (!isset(static::$instance)) {
            static::$instance = $this;
        }

        $this->_registerDefaultTypes();
    }

    protected function _registerDefaultTypes(){
        $this->add('json', function($value){
            return json_decode($value);
        });

        $this->add('serialized', function($value){
            return unserialize($value);
        });

        $this->add('unserialize', function($value){
            return unserialize($value);
        });

        $this->add('notempty', function($value){
            return (!empty($value));
        });

        $this->add('string', function($value){
            return strval($value);
        });

        $this->add('int', function($value){
            return intval($value);
        });

        $this->add('float', function($value){
            return floatval($value);
        });

        $this->add('double', function($value){
            return doubleval($value);
        });
    }

    public function add(string $name, Closure $closure){
        $this->compilers[$name] = $closure;
    }

    public function compile(string $method, $value){
        if(!empty($this->compilers[$method])){
            $closure = $this->compilers[$method];
            $value = $closure($value);
        }
        return $value;
    }

}