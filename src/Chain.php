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

    function __construct($handler, $middleware = [], $vars = [])
    {
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->vars = $vars;
    }

    public function execute($params, $middlewares = [], $converter = null)
    {
        $handler = $this->handler;
        $middleware = $this->middleware;

        $next = function () use ($handler, $converter) {
            return $this->call($handler, func_get_args(), $converter);
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
                    $next = function () use ($middlewareName, $middlewareArgs, $next, $middlewares, $converter) {
                        $params = func_get_args();
                        $params[] = $next;
                        $params[] = $middlewareArgs;
                        return $this->call($middlewares[$middlewareName], $params, $converter);
                    };
                } else {
                    throw new \Exception("middleware '{$middlewareName}' not found");
                }
            }
        }
        $params[] = $this->vars;
        return $next(...$params);

    }


    protected function call($callable, $params, $converter = null)
    {

        if (is_string($callable)) {
            if (strpos($callable, '::') !== false) {
                $callable = explode('::', $callable);
                if (!method_exists($callable[0], $callable[1])) {
                    throw new Exception\ChainValidException("Handler must be callable");
                }
            } elseif (strpos($callable, '@') !== false) {
                list($class, $method) = explode('@', $callable);

                if (!class_exists($class)) {
                    throw new Exception\ChainValidException("Handler must be callable(class {$class} not found)");
                }
                $class = new $class();
                if ($class instanceof ChainExecutor) {
                    $callable = [$class, 'execute'];
                    $params = [$method, $params];
                } elseif (!method_exists($class, $method)) {
                    throw new Exception\ChainValidException("Handler must be callable(method not found)");
                } else {
                    $callable = [$class, $method];
                }
            } elseif (function_exists($callable)) {
            } elseif (class_exists($callable)) {
                $callable = [new $callable(), 'handle'];
                if (!method_exists($callable[0], $callable[1])) {
                    throw new Exception\ChainValidException("Handler must be callable");
                }
            }
        }
        $result = Coroutine::call_user_func_array($callable, $params);
        if ($converter) {
            $result = Coroutine::call_user_func_array($converter, [$result]);
        }
        return $result;
    }
}