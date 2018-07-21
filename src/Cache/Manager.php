<?php
namespace Cabal\Core\Cache;

class Manager implements RepositoryInterface
{
    protected $pools = [];

    protected $repository = [];

    protected $config = [];

    protected $isTaskWorker;

    function __construct($config, $isTaskWorker)
    {
        $this->isTaskWorker = $isTaskWorker;
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
        $storeConfig = $this->config[$name];
        $driver = isset($storeConfig['driver']) ? $storeConfig['driver'] : $name;
        if ($driver instanceof \Closure) {
            $connection = $driver($storeConfig);
        } elseif (method_exists($this, "create" . ucfirst($driver) . 'Store')) {
            $method = "create" . ucfirst($driver) . 'Store';
            $connection = $this->$method($storeConfig);
        } elseif (class_exists($driver)) {
            $connection = new $driver($storeConfig);
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Invalid Cache driver "%s"; must be an defined driver(redis|file) or classname or \Closure',
                $driver
            ));
        }
        return new Repository($connection, $this->config['prefix']);
    }

    public function push($connection)
    {
        $this->pools[$connection->getId()]->push($connection);
    }

    protected function createRedisStore($config)
    {
        if (is_array($config['host'])) {
            $config['host'] = $config['host'][mt_rand(0, count($config['host']) - 1)];
        }
        $connectionId = "redis:" . http_build_query($config);
        if (!isset($this->pools[$connectionId])) {
            $this->pools[$connectionId] = new \SplQueue;
        }
        $connection = $this->pools[$connectionId]->isEmpty() ? null : $this->pools[$connectionId]->shift();

        if ($connection) {
            try {
                if (!$connection->isConnected()) {
                    echo "redis连接可能已断开: 没有响应\r\n";
                    $connection = null;
                }
            } catch (\Exception $ex) {
                $connection = null;
                echo "redis连接可能已断开:" . $ex->getMessage() . "\r\n"; 
                //@todo: log  
            }
        }
        if (!$connection) {
            if (!$this->isTaskWorker) {
                $connection = new Connection\CoroutineRedis();
                $connection->connect($config['host'], $config['port']);
                if (!$connection->isConnected()) {
                    throw new Exception("Redis连接失败:" . $connection->errMsg, $connection->errCode);
                }
                if (isset($config['auth']) && $config['auth']) {
                    $connection->auth($config['auth']);
                }
                $connection->setId($connectionId);
            } else {
                $connection = new Connection\Redis();
                try {
                    $connection->pconnect($config['host'], $config['port']);
                } catch (\Exception $ex) {
                    throw new Exception("Redis连接失败:" . $ex->getMessage(), $ex->getCode(), $ex);
                }
                if (isset($config['auth']) && $config['auth']) {
                    $connection->auth($config['auth']);
                }
                $connection->setId($connectionId);
            }
        }

        return new RedisCacheStore($this, $connection);
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
    public function ttl($key)
    {
        return $this->getDefaultRepo()->ttl($key);
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

    public function call($cmd, $argsOrArg1 = [])
    {
        $args = func_get_args();
        return $this->getDefaultRepo()->call(...$args);
    }

    public function __call($name, $args)
    {
        if (version_compare(phpversion('swoole'), '4.0.0', '<')) {
            throw new \Exception("Redis协程需要在 swoole 4.0.0 或以上才支持魔术方法");
        }
        array_unshift($args, $name);
        return $this->getDefaultRepo()->call(...$args);
    }
}