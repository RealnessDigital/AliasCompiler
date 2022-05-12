<?php
namespace AliasCompiler;

class ValueCompiler
{

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    protected function __clone(){}

    protected $compilers = [];

    protected function __construct(){
        $this->add(
            'json',
            function($value){
                return json_decode($value);
            }
        );

        $this->add(
            'serialized',
            function($value){
                return unserialize($value);
            }
        );

        $this->add(
            'notempty',
            function($value){
                return (!empty($value));
            }
        );
    }

    public function add($name, $closure){
        $this->compilers[$name] = $closure;
    }

    public function compile($method, $value){
        if(!empty($this->compilers[$method])){
            $closure = $this->compilers[$method];
            $value = $closure($value);
        }
        return $value;
    }

}