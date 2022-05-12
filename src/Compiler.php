<?php
namespace AliasCompiler;

use AliasCompiler\Helper\PhpFunctions;

class Compiler
{

    private static $instance = null;

    public static function getInstance(): Compiler
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    protected function __clone(){}



    protected $valueCompiler;

    public function __construct(){
        if (static::$instance === null) {
            static::$instance = $this;
        }

        $this->valueCompiler = ValueCompiler::getInstance();
    }

    public function getValueCompiler(){
        return $this->valueCompiler;
    }

    public function compile($response, $primary_keys = []){
        $compiled = [];

        foreach($response as $i => $row) {
            if(is_object($row)) $row = (array)$row;

            $item_id = $row[$primary_keys['root'] ?? 'id'];
            foreach($row as $key => $value){

                if(PhpFunctions::str_starts_with($key, '!')){
                    continue;
                } else if(PhpFunctions::str_starts_with($key, '?')){
                    if(is_null($value)){
                        continue;
                    } else {
                        $key = substr($key, 1);
                    }
                }

                $item = (!empty($compiled[$item_id]))? $compiled[$item_id] : [];
                $compiled[$item_id] = $this->addCompiledKeyAndValueToItem($item, $row, $key, $value, $primary_keys);

            }
        }

        return array_values($compiled);
    }

    protected function compileKeyAndValue($key, $value){
        if(PhpFunctions::str_contains($key, '>')){
            [$method, $key] = explode('>', $key);

            $value = $this->getValueCompiler()->compile($method, $value);
        }
        return [$key, $value];
    }

    protected function addCompiledKeyAndValueToItem($item, $row, $key, $value, $primary_keys){
        return $this->setValueToNestedKeys($item, $row, $key, $value, $primary_keys);
    }

    protected function setValueToNestedKeys($item, $row, $raw_key, $value, $primary_keys, $offset = 0){
        $keys = explode('@', $raw_key);
        $key = $keys[$offset];
        $next_key = ($offset+1 < count($keys))? $keys[$offset+1] : null;

        if(PhpFunctions::str_starts_with($key, '[]') || PhpFunctions::str_starts_with($key, '{}')){
            $asObject = PhpFunctions::str_starts_with($key, '{}');
            $key = substr($key, 2);
            if(!isset($item[$key])) {
                $item[$key] = ($asObject)? (object)[] : [];
            }

            $primary_key = $this->getPrimaryKeyAlias($keys, $offset, $primary_keys);
            if($primary_key){
                if(array_key_exists($primary_key, $row)){
                    $primary_key_value = $row[$primary_key];
                    if($primary_key_value){
                        if($asObject){
                            $new_item = (empty($item[$key][$primary_key_value]))? [] : $item[$key][$primary_key_value];
                            $item[$key][$primary_key_value] = $this->setValueToNestedKeys($new_item, $row, $raw_key, $value, $primary_keys, $offset+1);
                        } else {
                            $index = array_search($primary_key_value, array_column($item[$key], $primary_key)) ?? count($item[$key]);
                            $new_item = (isset($item[$key][$index]))? $item[$key][$index] : [];
                            $item[$key][$index] = $this->setValueToNestedKeys($new_item, $row, $raw_key, $value, $primary_keys, $offset+1);
                        }
                    }
                } else {
                    //throw new \Exception("No id for $raw_key found.");
                }
            } else {
                //throw new \Exception("Invalid multidimensional setting for $raw_key.");
            }
        } else {
            if(!is_null($next_key)){
                $new_item = (empty($item[$key]))? [] : $item[$key];
                $item[$key] = $this->setValueToNestedKeys($new_item, $row, $raw_key, $value, $primary_keys, $offset+1);
            } else {
                [$compiled_key, $value] = $this->compileKeyAndValue($key, $value);
                $item[$compiled_key] = $value;
            }
        }
        return $item;
    }

    protected function getPrimaryKeyAlias($keys, $key_index, $primary_keys){
        $keys = array_slice($keys, 0, $key_index+1);
        $array_key = implode('@', $keys);
        $primary_key = (!empty($primary_keys[$array_key]))? $primary_keys[$array_key] : 'id';
        return "$array_key@$primary_key";
    }

}
