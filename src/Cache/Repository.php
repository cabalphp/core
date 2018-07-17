<?php

namespace Cabal\Core\Cache;

class Repository
{
    /**
     *
     * @var \Cabal\Core\Cache\StoreInterface
     */
    protected $store;

    protected $prefix;

    function __construct($store, $prefix)
    {
        $this->store = $store;
        $this->prefix = $prefix;
    }

    public function key($key)
    {
        return $this->prefix . $key;
    }

    public function set($key, $val, $minutes)
    {
        $this->store->set($this->key($key), $val, $minutes);
        return $val;
    }

    public function forever($key, $val)
    {
        $this->store->forever($this->key($key), $val);
    }

    public function get($key, $default = null)
    {
        return $this->store->get($this->key($key));
    }

    public function forget($key)
    {
        return $this->del($key);
    }

    public function del($key)
    {
        $this->store->del($this->key($key));
    }

    public function increment($key, $amount = 1)
    {
        return $this->store->increment($this->key($key), $amount);
    }

    public function decrement($key, $amount = 1)
    {
        return $this->store->decrement($this->key($key), $amount);
    }

    public function pull($key, $default = null)
    {
        $val = $this->get($this->key($key), $default);
        $this->del($key);
        return $val;
    }

    public function remember($key, $minutes, \Closure $callback)
    {
        return $this->get($key) ? : $this->set($key, $callback(), $minutes);;
    }

    public function call($cmd, $argsOrArg1 = [])
    {
        $args = func_get_args();
        return $this->store->call(...$args);
    }

    public function __call($name, $args)
    {
        if (version_compare(phpversion('swoole'), '4.0.0', '<')) {
            throw new \Exception("Redis协程需要在 swoole 4.0.0 或以上才支持魔术方法");
        }
        array_unshift($args, $name);
        return $this->store->call(...$args);
    }
}