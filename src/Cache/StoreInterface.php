<?php

namespace Cabal\Core\Cache;

interface StoreInterface
{
    public function __construct(Manager $manager, $config);

    public function set($key, $val, $minutes);
    public function forever($key, $val);
    public function get($key);
    public function del($key);
    public function ttl($key);
    public function increment($key, $amount = 1);
    public function decrement($key, $amount = 1);
}