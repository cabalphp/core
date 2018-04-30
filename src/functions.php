<?php

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