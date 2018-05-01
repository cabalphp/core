<?php
namespace Cabal\Core;

use Swoole\Coroutine;
use Psr\Http\Message\ResponseInterface;
use Cabal\Core\Http\Response;

class Chain
{
    protected $handler;

    protected $middleware;

    protected $vars;

    function __construct($handler, $middleware, $vars = [])
    {
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->vars = $vars;
    }

    public function execute($params, $middlewares = [])
    {
        $handler = $this->handler;
        $middleware = $this->middleware;

        $next = function () use ($handler) {
            return $this->call($handler, func_get_args());
        };

        if ($middleware) {
            $middleware = (array)$middleware;
            foreach ($middleware as $middlewareName => $middlewareArgs) {
                if (is_int($middlewareName)) {
                    $middlewareName = $middlewareArgs;
                    $middlewareArgs = [];
                } else {
                    $middlewareArgs = (array)$middlewareArgs;
                }
                if (isset($middlewares[$middlewareName])) {
                    $next = function () use ($middlewareName, $middlewareArgs, $next, $middlewares) {
                        $params = func_get_args();
                        $params[] = $next;
                        $params[] = $middlewareArgs;
                        return $this->call($middlewares[$middlewareName], $params);
                    };
                } else {
                    throw new \Exception("middleware '{$middlewareName}' not found");
                }
            }
        }
        $params[] = $this->vars;
        return $next(...$params);

    }


    protected function call($callable, $params)
    {

        if (is_string($callable)) {
            if (strpos($callable, '::') !== false) {
                $callable = explode('::', $callable);
                if (!method_exists($callable[0], $callable[1])) {
                    throw new Exception\ChainValidException("Handler must be callable");
                }
            } elseif (strpos($callable, '@') !== false) {
                list($controllerName, $method) = explode('@', $callable);
                $callable = [new $controllerName(), $method];
                if (!method_exists($callable[0], $callable[1])) {
                    throw new Exception\ChainValidException("Handler must be callable");
                }
            } elseif (function_exists($callable)) {
            } elseif (class_exists($callable)) {
                $callable = [new $callable(), 'handle'];
                if (!method_exists($callable[0], $callable[1])) {
                    throw new Exception\ChainValidException("Handler must be callable");
                }
            }
        }
        return Coroutine::call_user_func_array($callable, $params);
    }
}