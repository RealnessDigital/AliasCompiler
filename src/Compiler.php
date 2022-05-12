<?php
namespace AliasCompiler;

use AliasCompiler\Helper\PhpFunctions;

class Compiler
{

    private static Compiler $instance;

    public static function getInstance(): Compiler
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }



    protected ValueCompiler $valueCompiler;

    public function __construct(){
        if (!isset(static::$instance)) {
            static::$instance = $this;
        }

        $this->valueCompiler = ValueCompiler::getInstance();
    }

    public function getValueCompiler(): ValueCompiler
    {
        return $this->valueCompiler;
    }

    public function compile($data, array $primary_keys = []): array
    {
        $response = $this->getResponse($data);
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
                    }
                    $key = substr($key, 1);
                }

                $item = (!empty($compiled[$item_id]))? $compiled[$item_id] : [];
                $compiled[$item_id] = $this->addCompiledKeyAndValueToItem($item, $row, $key, $value, $primary_keys);

            }
        }

        return array_values($compiled);
    }

    protected function getResponse($data): array
    {
        $class = get_class($data);
        $response = [];

        if($class == 'mysqli_result'){
            while($data && $row = mysqli_fetch_assoc($data)){
                $response[] = $row;
            }
        } else if(is_object($data) && method_exists($data, 'toArray')){
            $response = call_user_func([$data, 'toArray']);
        } else {
            $response = (array)$data;
        }

        return $response;
    }

    protected function compileKeyAndValue($key, $value): array
    {
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

            [$raw_primary_key, $primary_key] = $this->getObjectPrimaryKey($keys, $offset, $primary_keys);
            if($raw_primary_key){
                if(array_key_exists($raw_primary_key, $row)){
                    $primary_key_value = $row[$raw_primary_key];
                    if($primary_key_value){
                        if($asObject){
                            $new_item = (!empty($item[$key]->{$primary_key_value}))? $item[$key]->{$primary_key_value} : [];
                            $item[$key]->{$primary_key_value} = $this->setValueToNestedKeys($new_item, $row, $raw_key, $value, $primary_keys, $offset+1);
                        } else {
                            $index = array_search($primary_key_value, array_column($item[$key], $primary_key));
                            if($index === false) $index = count($item[$key]);
                            $new_item = ($index < count($item[$key]))? $item[$key][$index] : [$primary_key => $primary_key_value];
                            $item[$key][$index] = $this->setValueToNestedKeys($new_item, $row, $raw_key, $value, $primary_keys, $offset+1);
                        }
                    }
                } else {
                    throw new \Exception("Primary key of '$raw_key' not found");
                }
            } else {
                throw new \Exception("Invalid multidimensional configuration for '$raw_key'");
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

    protected function getObjectPrimaryKey($keys, $key_index, $primary_keys): array
    {
        $array_key = implode('@', array_slice($keys, 0, $key_index+1));
        $primary_key = (!empty($primary_keys[$array_key]))? $primary_keys[$array_key] : 'id';
        return ["$array_key@$primary_key", $primary_key];
    }

}