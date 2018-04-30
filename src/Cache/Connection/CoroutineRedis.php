<?php

namespace Cabal\Core\Cache\Connection;

class CoroutineRedis extends \Swoole\Coroutine\Redis
{
    protected $id;
    protected $name;

    public function isConnected()
    {
        return $this->connected;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getName()
    {
        return $this->name;
    }
}