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

function cabalCall($callable, $args = [])
{
    if (is_string($callable)) {
        if (strpos($callable, '::') !== false) {
            $callable = explode('::', $callable);
        } elseif (strpos($callable, '@') !== false) {
            list($controllerName, $method) = explode('@', $callable);
            $callable = [new $controllerName(), $method];
        }
    }
    if ($callable instanceof \Closure) {
        return $callable(...$args);
    } elseif (is_array($callable)) {
        if (is_object($callable[0])) {
            return $callable[0]->$callable[1](...$args);
        } elseif (is_string($callable[0])) {
            return $callable[0]::$callable[1](...$args);
        }
    } elseif (is_string($callable) && function_exists($callable)) {
        return $callable(...$args);
    }

    throw new \Exception('handler' . var_export($callable, true) . " isn't callable");
}