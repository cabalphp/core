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

    public function __destruct()
    {
        if ($this->redis->getId()) {
            $this->manager->push($this->redis);
        }
    }

}