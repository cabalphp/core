<?php
namespace Cabal\Core\Application;

use Zend\Diactoros\ServerRequest;
use Cabal\Core\Http\Server;
use Cabal\Route\RouteCollection;
use Cabal\Route\Route;
use Cabal\Core\Http\Exception\MethodNotAllowedException;
use Psr\Http\Message\RequestInterface;
use Cabal\Core\Http\Exception\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Cabal\Core\Http\Request;


class Dispatcher
{
    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\Http\Server
     */
    protected $server;

    protected $middlewares = [];

    protected $missingChain;

    protected $methodNotAllowedChain;

    protected $exceptionHandler;

    /**
     * Undocumented variable
     *
     * @var \Cabal\Route\RouteCollection
     */
    protected $route;

    public function __construct()
    {
    }

    public function setServer(Server $server)
    {
        $this->server = $server;
        return $this;
    }

    public function onStart(Server $server)
    {
        echo "Server@" . $this->server->boot()->env() . " is started at http://{$server->host}:{$server->port}\r\n";
    }

    public function onWorkerStart(Server $server, $workerId)
    {
        $this->route = new RouteCollection();
        $routes = $this->server->boot()->configure('cabal.routes', [
            'usr/routes.php',
        ]);
        foreach ($routes as $routePath) {
            $routePath = $this->server->boot()->rootPath($routePath);
            if (file_exists($routePath)) {
                $route = $this->route;
                $dispatcher = $this;
                require $routePath;
            } else {
                throw new \Exception("route file '{$routePath}' not exists");;
            }
        }
    }

    public function onRequest($swooleRequest, $swooleResponse)
    {
        $scheme = strtolower(current(explode('/', $swooleRequest->server['server_protocol'])));
        $fullUri = implode('', [$scheme, '://', $swooleRequest->header['host'], $swooleRequest->server['request_uri']]);
        $request = new Request(
            $swooleRequest->server,
            $swooleRequest->files ? : [],
            $fullUri,
            $swooleRequest->server['request_method'],
            $swooleRequest->rawContent() ? : fopen('/dev/null', 'r'),
            $swooleRequest->header ? : [],
            $swooleRequest->cookie ? : [],
            $swooleRequest->get ? : [],
            $swooleRequest->post ? : [],
            str_replace('HTTP/', '', $swooleRequest->server['server_protocol'])
        );
        list($code, $chain, $vars) = $this->route->dispatch($request);

        switch ($code) {
            case Route::NOT_FOUND:
                $response = $this->notFoundResponse($request);
                break;
            case Route::METHOD_NOT_ALLOWED:
                $response = $this->methodNotAllowedResponse($request);
                break;
            case Route::FOUND:
                try {
                    $response = $this->execute($chain, $request, $vars);
                } catch (\Exception $ex) {
                    $response = $this->handlerException($ex, $chain, $request, $vars);
                }
                break;
        }

        $swooleResponse->status($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->header($name, $value);
            }
        }

        if (method_exists($response, 'getCookies')) {
            foreach ($response->getCookies() as $cookie) {
                $swooleResponse->cookie(...$cookie);
            }
        }
        $swooleResponse->end($response->getBody());

    }

    public function addMiddleware($nameOrMiddlewares, $handler = null)
    {
        if (is_array($nameOrMiddlewares)) {
            foreach ($nameOrMiddlewares as $name => $middleware) {
                $this->addMiddleware($name, $middleware);
            }
        } else {
            $this->middlewares[$nameOrMiddlewares] = $handler;
        }
        return $this;
    }


    /**
     * Undocumented function
     *
     * @param [type] $chain
     * @param RequestInterface $request
     * @param [type] $vars
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function execute($chain, RequestInterface $request, $vars)
    {
        $handler = $chain['handler'];
        $middleware = $chain['middleware'];

        $next = function (Server $server, RequestInterface $request, $vars) use ($handler) {
            $response = $this->call($handler, [$server, $request, $vars]);
            return $this->response($response);
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
                if (isset($this->middlewares[$middlewareName])) {
                    $next = function (Server $server, RequestInterface $request, $vars) use ($middlewareName, $middlewareArgs, $next) {
                        $response = $this->call($this->middlewares[$middlewareName], [$server, $request, $vars, $next, $middlewareArgs]);
                        return $this->response($response);
                    };
                } else {
                    throw new \Exception("middleware '{$middlewareName}' not found");
                }
            }
        }
        return $next($this->server, $request, $vars, null, []);
    }


    public function response($response)
    {
        if (!($response instanceof ResponseInterface)) {
            $body = '';
            if (is_array($response) || $response instanceof \stdClass || $response instanceof \JsonSerializable) {
                $body = json_encode($response);
            } elseif (is_object($response) && method_exists($response, 'render')) {
                $body = $response->render();
            } elseif (is_string($response) || is_numeric($response)) {
                $body = $response;
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid response "%s"; must be an string or object has render method',
                    gettype($response)
                ));
            }
            $response = new Response();
            $response->getBody()->write($body);
        }
        return $response;
    }

    public function call($callable, $args = [])
    {
        if (is_string($callable)) {
            if (strpos($callable, '::') !== false) {
                $callable = explode('::', $callable);
            } elseif (strpos($callable, '@') !== false) {
                list($controllerName, $method) = explode('@', $callable);
                $callable = [new $controllerName(), $method];
            }
        }
        if ($callable instanceof \Closure) {
            return $callable(...$args);
        } elseif (is_array($callable)) {
            list($controller, $method) = $callable;
            if (is_object($controller)) {
                return $controller->$method(...$args);
            } elseif (is_string($controller)) {
                return $controller::$method(...$args);
            }
        } elseif (is_string($callable)) {
            if (function_exists($callable)) {
                return $callable(...$args);
            } elseif (class_exists($callable)) {
                $callable = new $callable();
                return $callable->handle(...$args);
            }
        }

        throw new \Exception('handler' . var_export($callable, true) . " isn't callable");
    }

    /**
     * Undocumented function
     *
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function notFoundResponse(RequestInterface $request)
    {
        if ($this->missingChain) {
            return $this->execute($this->missingChain, $request, []);
        }
        $response = new Response('php://memory', 404);
        $response->getBody()->write('<html><head><title>404 Not Found</title></head><body bgcolor="white"><h1>404 Not Found</h1></body></html>');
        return $response;
    }

    /**
     * Undocumented function
     *
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function methodNotAllowedResponse(RequestInterface $request)
    {
        if ($this->methodNotAllowedChain) {
            return $this->execute($this->methodNotAllowedChain, $request, []);
        }
        $response = new Response('php://memory', 405);
        $response->getBody()->write('<html><head><title>405 Method Not Allowed</title></head><body bgcolor="white"><h1>405 Method Not Allowed</h1></body></html>');
        return $response;
    }

    public function handlerException(\Exception $ex, $chain, $request, $vars)
    {
        if ($this->exceptionHandler) {
            return $this->call($this->exceptionHandler, [$this->server, $ex, $chain, $request, $vars]);
        }
        $response = new Response('php://memory', 500);
        $body = '';
        if ($this->server->debug()) {
            $body = '<pre>' . $ex->__toString() . '</pre>';
        }
        $response->getBody()->write('<html><head><title>500 Internal Server Error</title></head><body bgcolor="white"><h1>500 Internal Server Error</h1>' . $body . '</body></html>');
        return $response;
    }

    public function registerMissingHandler($callableOrChain, $vars)
    {
        $this->missingChain = isset($callableOrChain['handler']) ? $callableOrChain : [
            'handler' => $callableOrChain,
            'middleware' => [],
        ];
        return $this;
    }

    public function registerMethodNotAllowHandler($callableOrChain, $vars)
    {
        $this->methodNotAllowedChain = isset($callableOrChain['handler']) ? $callableOrChain : [
            'handler' => $callableOrChain,
            'middleware' => [],
        ];
        return $this;
    }

    public function registerExceptionHandler($handler)
    {
        $this->exceptionHandler = $handler;
        return $this;
    }


}