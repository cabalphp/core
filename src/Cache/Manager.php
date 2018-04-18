<?php

namespace Cabal\Core\Cache;

class Manager implements RepositoryInterface
{
    protected $repository = [];

    protected $config = [];

    function __construct($config)
    {
        $this->config = array_merge([
            'default' => 'file',
            'prefix' => '',
        ], $config);
    }

    /**
     * Undocumented function
     *
     * @return \Cabal\Core\Cache\StoreInterface
     */
    public function getDefaultRepo()
    {
        return $this->getRepository($this->config['default']);
    }


    /**
     * Undocumented function
     *
     * @param string $name
     * @return \Cabal\Core\Cache\Repository
     */
    public function getRepository($name)
    {
        if (!isset($this->repository[$name])) {
            $storeConfig = $this->config[$name];
            $driver = isset($storeConfig['driver']) ? $storeConfig['driver'] : $name;
            if ($driver instanceof \Closure) {
                $store = $driver($storeConfig);
                $this->repository[$name] = new Repository($store, $this->config['prefix']);
            } elseif (class_exists($driver)) {
                $store = new $driver($storeConfig);
                $this->repository[$name] = new Repository($store, $this->config['prefix']);
            } elseif (method_exists($this, "create" . ucfirst($name) . 'Store')) {
                $method = "create" . ucfirst($name) . 'Store';
                $store = $this->$method($storeConfig);
                $this->repository[$name] = new Repository($store, $this->config['prefix']);
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid Cache driver "%s"; must be an defined driver(redis|file) or classname or \Closure',
                    gettype($response)
                ));
            }
        }
        return $this->repository[$name];

    }

    protected function createRedisStore($config)
    {
        return new RedisCacheStore($config);
    }

    public function key($key)
    {
        return $this->getDefaultRepo()->key($key);
    }

    public function set($key, $val, $minutes)
    {
        return $this->getDefaultRepo()->set($key, $val, $minutes);
    }

    public function forever($key, $val)
    {
        return $this->getDefaultRepo()->forever($key, $val);
    }

    public function add($key, $val, $minutes)
    {
        return $this->getDefaultRepo()->add($key, $val, $minutes);
    }

    public function get($key, $default = null)
    {
        return $this->getDefaultRepo()->get($key, $default);
    }

    public function forget($key)
    {
        return $this->getDefaultRepo()->forget($key);
    }

    public function del($key)
    {
        return $this->getDefaultRepo()->del($key);
    }

    public function increment($key, $amount = 1)
    {
        return $this->getDefaultRepo()->increment($key, $amount);
    }

    public function decrement($key, $amount = 1)
    {
        return $this->getDefaultRepo()->decrement($key, $amount);
    }

    public function pull($key, $default = null)
    {
        return $this->getDefaultRepo()->pull($key, $default);
    }

    public function remember($key, $minutes, \Closure $callback)
    {
        return $this->getDefaultRepo()->remember($key, $minutes, $callback);
    }
}