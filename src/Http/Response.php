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


    /**
     * Undocumented function
     *
     * @param [type] $body
     * @param integer $statusCode
     * @return \Cabal\Core\Http\Response
     */
    static function make($body, $statusCode = 200)
    {
        $r = new Response();
        $r = $r->withStatus($statusCode);
        $r->getBody()->write($body);
        return $r;
    }


}