<?php

function snake_case($string)
{
    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
        $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
    }
    return implode('_', $ret);
}

function snake_keys($array)
{
    $result = [];
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $value = snake_keys($value);
        }
        $result[snake_case($key)] = $value;
    }
    return $result;
}
function camel_case($string)
{
    $string = ucwords(str_replace(array('-', '_'), ' ', $string));
    return lcfirst(str_replace(' ', '', $string));
}

function camel_keys($array)
{
    $result = [];
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $value = camel_keys($value);
        }
        $result[camel_case($key)] = $value;
    }
    return $result;
}

function array_except($array, $keys)
{
    return array_diff_key($array, array_flip((array)$keys));
}

function array_only($array, $keys)
{
    return array_intersect_key($array, array_flip((array)$keys));
}

function array_dot($arr, $prefix = '')
{
    $res = [];

    foreach ($arr as $key => $val) {
        if (is_array($val) && !empty($val)) {
            $res = array_merge($res, array_dot($val, $prefix . $key . '.'));
        } else {
            $res[$prefix . $key] = $val;
        }
    }

    return $res;
}

function array_get($array, $name, $default = null)
{
    foreach ($name ? explode('.', $name) : [] as $key) {
        if (isset($array[$key])) {
            $array = $array[$key];
        } else {
            return $default;
        }
    }
    return $array;
}

if (!function_exists('with')) {
    function with($obj)
    {
        return $obj;
    }
}