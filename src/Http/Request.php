<?php
namespace Cabal\Core\Http;

use Zend\Diactoros\ServerRequest;

class Request extends ServerRequest
{
    public function ip()
    {
        return array_get($this->getServerParams(), 'remote_addr');
    }

    public function isXhr()
    {
        return strtolower($this->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    public function isMethod($method)
    {
        return strtoupper($method) === $this->getMethod();
    }

    public function input($name, $default = null)
    {
        return array_get(
            (array)$this->getParsedBody(),
            $name,
            array_get(
                (array)$this->getQueryParams(),
                $name,
                $default
            )
        );
    }

    public function file($name)
    {
        return array_get(
            (array)$this->getUploadedFiles(),
            $name
        );
    }

    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return array_merge(
            array_only($this->getQueryParams(), $keys),
            array_only($this->getParsedBody(), $keys)
        );
    }

    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return array_merge(
            array_except($this->getQueryParams(), $keys),
            array_except($this->getParsedBody(), $keys)
        );
    }

    public function all()
    {
        return array_merge(
            $this->getQueryParams(),
            $this->getParsedBody()
        );
    }

    public function has($name)
    {
        return array_key_exists($name, (array)$this->getParsedBody()) ||
            array_key_exists($name, (array)$this->getQueryParams());
    }

    public function filled($name)
    {
        return !empty($this->input($name)) || strlen($this->input($name)) > 0;
    }

    public function cookie($name, $default = null)
    {
        return array_get($this->getCookieParams(), $name, $default);
    }

    /**
     * Undocumented function
     *
     * @param [type] $name
     * @param [type] $val
     * @return \Cabal\Core\Session
     */
    public function session($name = null, $val = null)
    {
        $session = $this->getAttribute('session');
        switch (count(func_get_args())) {
            case 0:
                return $session;
                break;
            case 1:
                return $session->offsetGet($name);
                break;
            case 2:
                $session->offsetSet($name, $val);
                return $session;
                break;
            default:
                throw new \InvalidArgumentException(sprintf(
                    'Invalid args num "%d"',
                    count(func_get_args())
                ));
                break;
        }
    }


    public function fd()
    {
        return $this->getAttribute('fd');
    }
    /**
     * Undocumented function
     *
     * @param [type] $name
     * @param [type] $val
     * @return \Cabal\Core\Session 
     */
    public function fdSession($name = null, $val = null)
    {
        $fdSession = $this->getAttribute('fdSession');
        switch (count(func_get_args())) {
            case 0:
                return $fdSession;
                break;
            case 1:
                return $fdSession->offsetGet($name);
                break;
            case 2:
                $fdSession->offsetSet($name, $val);
                return $fdSession;
                break;
            default:
                throw new \InvalidArgumentException(sprintf(
                    'Invalid args num "%d"',
                    count(func_get_args())
                ));
                break;
        }
    }
}