<?php

namespace Cabal\Core\Cache;

class RedisCacheStore implements StoreInterface
{
    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\Cache\Manager
     */
    protected $manager;

    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\Cache\Coroutine\Redis
     */
    protected $redis;

    public function __construct(Manager $manager, $redis)
    {
        $this->manager = $manager;
        $this->redis = $redis;
    }

    public function set($key, $val, $minutes)
    {
        $this->redis->set($key, serialize($val));
        $this->redis->expire($key, $minutes * 60);
    }
    public function forever($key, $val)
    {
        $this->redis->set($key, serialize($val));
    }
    public function get($key)
    {
        $val = $this->redis->get($key);
        return $val ? unserialize($val) : $val;
    }
    public function del($key)
    {
        $this->redis->del($key);
    }
    public function ttl($key)
    {
        return $this->redis->ttl($key);
    }
    public function increment($key, $amount = 1)
    {
        return $this->redis->incrby($key, $amount);
    }
    public function decrement($key, $amount = 1)
    {
        return $this->redis->decrby($key, $amount);
    }

    public function call($cmd, $argsOrArg1 = [])
    {
        if (is_array($argsOrArg1) && func_num_args() === 2) {
            return $this->redis->$cmd(...$argsOrArg1);
        } else {
            $args = func_get_args();
            $cmd = array_shift($args);
            return $this->redis->$cmd(...$args);
        }
    }

    public function __destruct()
    {
        if ($this->redis->getId()) {
            $this->manager->push($this->redis);
        } else {
            $this->redis->close();
        }
    }

}