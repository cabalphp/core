<?php
namespace Cabal\Core\Http;

use Zend\Diactoros\Response as BaseResponse;

class Response extends BaseResponse
{
    protected $cookies = [];

    public function withCookie($key, $value = '', $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
    {
        $newResponse = clone $this;
        $newResponse->cookies[] = func_get_args();
        return $newResponse;
    }

    public function getCookies()
    {
        return $this->cookies;
    }

}