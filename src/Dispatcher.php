<?php
namespace Cabal\Core;

use Zend\Diactoros\ServerRequest;
use Cabal\Core\Server;
use Cabal\Route\RouteCollection;
use Cabal\Route\Route;
use Cabal\Core\Http\Exception\MethodNotAllowedException;
use Psr\Http\Message\RequestInterface;
use Cabal\Core\Http\Exception\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Cabal\Core\Http\Request;
use Swoole\Coroutine;


class Dispatcher
{
    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\Server
     */
    protected $server;

    protected $middlewares = [];

    protected $missingChain;

    protected $methodNotAllowedChain;

    protected $exceptionChain;

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
        echo "Server@" . $this->server->env() . " is started at http://{$server->host}:{$server->port}\r\n";
    }

    public function onReceive(Server $server, $fd, $reactor_id, $data)
    {
    }

    public function onPacket(Server $server, $data, $client_info)
    {
    }

    public function onClose(Server $server, $data, $client_info)
    {
    }

    public function onTask(Server $server, $taskId, $workerId, $data)
    {
        $chain = $this->newChain($data);
        $response = $chain->execute([$server, $taskId, $workerId]);
        if ($response) {
            return $response;
        }
    }

    public function onFinish(Server $server, $taskId, $data)
    {
        print_r($data);
    }

    public function onPipeMessage(Server $server, $workerId, $message)
    {
    }

    public function onWorkerStart(Server $server, $workerId)
    {
        $this->route = new RouteCollection();
        $routes = $this->server->configure('cabal.routes', [
            'usr/routes.php',
        ]);
        foreach ($routes as $routePath) {
            $routePath = $this->server->rootPath($routePath);
            if (file_exists($routePath)) {
                $route = $this->route;
                $dispatcher = $this;
                require $routePath;
            } else {
                throw new \Exception("route file '{$routePath}' not exists");;
            }
        }
        if (!$this->server->taskworker) {
            //*
            $server->tick(1000, function () use ($server) {
                $server->task([
                    'handler' => 'TestController@task',
                    'middleware' => [],
                    'vars' => [1, 2, 3, 4]
                ], 0);
            });
            //*/
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
                $chain = new Chain($chain['handler'], $chain['middleware'], $vars);
                try {
                    $response = $chain->execute([$this->server, $request]);
                } catch (\Exception $ex) {
                    $response = $this->handlerException($ex, $chain, $request);
                }
                break;
        }
        $response = $this->response($response);

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
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function notFoundResponse(RequestInterface $request)
    {
        if ($this->missingChain) {
            return $this->missingChain->execute(
                [$this->server, $request],
                $this->middlewares
            );
        }
        return '<html><head><title>404 Not Found</title></head><body bgcolor="white"><h1>404 Not Found</h1></body></html>';
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
            return $this->methodNotAllowedChain->execute(
                [$this->server, $request],
                $this->middlewares
            );
        }
        return '<html><head><title>405 Method Not Allowed</title></head><body bgcolor="white"><h1>405 Method Not Allowed</h1></body></html>';
    }

    public function handlerException(\Exception $ex, $chain, $request)
    {
        if ($this->exceptionChain) {
            return $this->exceptionChain->execute(
                [$this->server, $ex, $chain, $request],
                $this->middlewares
            );
        }
        $body = '';
        if ($this->server->debug()) {
            $body = '<pre>' . $ex->__toString() . '</pre>';
        }
        return '<html><head><title>500 Internal Server Error</title></head><body bgcolor="white"><h1>500 Internal Server Error</h1>' . $body . '</body></html>';
    }

    protected function response($response)
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


    public function registerMissingHandler($callableOrChain)
    {
        $this->missingChain = $this->newChain($callableOrChain);
        return $this;
    }

    public function registerMethodNotAllowHandler($callableOrChain)
    {
        $this->methodNotAllowedChain = $this->newChain($callableOrChain);
        return $this;
    }

    public function registerExceptionHandler($callableOrChain)
    {
        $this->exceptionChain = $this->newChain($callableOrChain);
        return $this;
    }

    protected function newChain($callableOrChain)
    {
        if ($callableOrChain instanceof Chain) {
            return $callableOrChain;
        } elseif (is_array($callableOrChain) && isset($callableOrChain['handler'])) {
            return new Chain(
                $callableOrChain['handler'],
                isset($callableOrChain['middleware']) ? $callableOrChain['middleware'] : [],
                isset($callableOrChain['vars']) ? $callableOrChain['vars'] : []
            );
        } elseif (is_string($callableOrChain)) {
            return new Chain($callableOrChain, [], []);
        } elseif (is_callable($callableOrChain)) {
            return new Chain($callableOrChain, [], []);
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Invalid chain "%s"; must be callable',
                gettype($response)
            ));
        }
    }


}