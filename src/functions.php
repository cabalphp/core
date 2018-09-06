<?php

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
        var_dump($result);
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