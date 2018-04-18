<?php

namespace Cabal\Core\Cache;

class RedisCacheStore implements StoreInterface
{
    protected $redis;

    public function __construct($config)
    {
        $this->redis = new \Swoole\Coroutine\Redis();
        $this->redis->connect($config['host'], $config['port']);
        if ($config['auth']) {
            $this->redis->auth($config['auth']);
        }
    }

    public function set($key, $val, $minutes)
    {
        $this->redis->set($key, $val);
        $this->redis->expire($key, $minutes * 60);
    }
    public function forever($key, $val)
    {
        $this->redis->set($key, $val);
    }
    public function get($key)
    {
        return $this->redis->get($key);
    }
    public function del($key)
    {
        $this->redis->del($key);
    }
    public function increment($key, $amount = 1)
    {
        return $this->redis->incrby($key, $amount);
    }
    public function decrement($key, $amount = 1)
    {
        return $this->redis->decrby($key, $amount);
    }
}