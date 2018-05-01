<?php
namespace Cabal\Core;

use Cabal\Core\SessionHandler\AbstractSessionHandler;


class Session implements \Iterator, \ArrayAccess, \Countable, \JsonSerializable
{
    protected $sessionId;

    protected $data;

    protected $config;
    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\SessionHandler\AbstractSessionHandler
     */
    protected $handler;

    protected $defaultConfig = [
        'save_path' => '',
        'name' => 'session_id',
        'maxlifetime' => 1440,
        'cookie_lifetime' => 0,
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => false,
        'cookie_httponly' => true,
        'filter' => [],
    ];

    public function __construct(AbstractSessionHandler $handler, $sessionId, $config = [])
    {
        $this->handler = $handler;
        $this->sessionId = $sessionId;
        $this->config = array_merge($this->defaultConfig, $config);

        $handler->open($this->config['save_path'], $this->config['name']);
        $handler->gc($this->config['maxlifetime']);

        $this->data = $this->handler->read($this->sessionId);
        $this->data = $this->data ? unserialize($this->data) : [];
    }

    public function config($name, $default = null)
    {
        return isset($this->config[$name]) ? $this->config[$name] : $default;
    }

    public function sessionId()
    {
        return $this->sessionId;
    }

    public function offsetExists($key)
    {
        return isset($this->data[$key]);
    }

    public function offsetGet($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function offsetSet($key, $value)
    {
        $this->data[$key] = $value;
    }
    public function offsetUnset($key)
    {
        unset($this->data[$key]);
    }

    public function count()
    {
        return count($this->data);
    }
    public function current()
    {
        return current($this->data);
    }
    public function key()
    {
        return key($this->data);
    }
    public function next()
    {
        next($this->keys);
    }
    public function rewind()
    {
        reset($this->data);
    }
    public function valid()
    {
        return current($this->data) !== false;
    }

    public function toArray()
    {
        return array_filter($this->data, function ($key) {
            return !in_array($key, $this->config['filter']);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function delete()
    {
        $this->handler->destroy($this->sessionId);
    }

    public function write()
    {
        $this->handler->write($this->sessionId, serialize($this->data));
    }

}