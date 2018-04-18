<?php

namespace Cabal\Core\Cache;

interface StoreInterface
{
    public function __construct($config);

    public function set($key, $val, $minutes);
    public function forever($key, $val);
    public function get($key);
    public function del($key);
    public function increment($key, $amount = 1);
    public function decrement($key, $amount = 1);
}