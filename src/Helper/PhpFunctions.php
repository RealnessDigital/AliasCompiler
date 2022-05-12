<?php
namespace AliasCompiler\Helper;

class PhpFunctions
{

    public static function str_starts_with($haystack, $needle){
        if(function_exists('str_starts_with')){
            return str_starts_with($haystack, $needle);
        } else {
            return substr($haystack, 0, strlen($needle)) === $needle;
        }
    }

    public static function str_contains($haystack, $needle){
        if(function_exists('str_contains')){
            return str_contains($haystack, $needle);
        } else {
            return strpos($haystack, $needle) !== false;
        }
    }

}