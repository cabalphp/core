<?php
namespace Cabal\Core\Http;

class Headers implements \Iterator, \ArrayAccess, \Countable, \JsonSerializable
{
    protected $headers;

    public function __construct($headers = [])
    {
        $this->headers = $headers;
    }

    public function __get($name)
    {
        return $this->offsetGet($name);
    }
    public function __set($name, $value)
    {
        $this->offsetSet($name, $val);
    }

    public function count()
    {
        return count($this->headers);
    }
    public function current()
    {
        return current($this->headers);
    }
    public function key()
    {
        return key($this->headers);
    }
    public function next()
    {
        next($this->headers);
    }
    public function rewind()
    {
        reset($this->headers);
    }
    public function valid()
    {
        return current($this->headers) !== false;
    }

    public function offsetExists($key)
    {
        return isset($this->headers[$key]);
    }

    public function offsetGet($key)
    {
        return isset($this->headers[$key]) ? $this->headers[$key] : null;
    }

    public function offsetSet($key, $value)
    {
        $this->headers[$key] = $value;
    }
    public function offsetUnset($key)
    {
        unset($this->headers[$key]);
    }

    public function toArray()
    {
        return $this->headers;
    }

    public function jsonSerialize()
    {
        return $this->headers;
    }
}