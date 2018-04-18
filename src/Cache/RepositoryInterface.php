<?php

namespace Cabal\Core\Cache;

interface RepositoryInterface
{
    public function key($key);
    public function set($key, $val, $minutes);
    public function forever($key, $val);
    public function get($key, $default = null);
    public function forget($key);
    public function del($key);
    public function increment($key, $amount = 1);
    public function decrement($key, $amount = 1);
    public function pull($key, $default = null);
    public function remember($key, $minutes, \Closure $callback);
}