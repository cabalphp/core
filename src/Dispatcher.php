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
use Cabal\Core\Http\Request;
use Swoole\Coroutine;
use Cabal\Core\Http\Headers;
use Cabal\Core\Session;
use Cabal\Core\SessionHandler\ArraySessionHandler;
use Cabal\Core\Http\Frame;
use Cabal\Core\Http\Response;
use Cabal\Core\Exception\BadRequestException;


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


        $server->on('start', [$this, 'onStart']);
        $server->on('close', [$this, 'onClose']);
        $server->on("workerStart", [$this, 'onWorkerStart']);
        $server->on('request', [$this, 'onRequest']);
        $server->on('message', [$this, 'onMessage']);
        $server->on('handShake', [$this, 'onHandShake']);
        $server->on('task', [$this, 'onTask']);
        $server->on('finish', [$this, 'onFinish']);

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

    public function onClose(Server $server, $fd, $reactorId)
    {
        $connectionInfo = $server->connection_info($fd);
        if (isset($connectionInfo['websocket_status'])) {
            $fdSession = new Session($this->server->fdSessionHandler(), $fd, [
                'filter' => ['__chain', '__vars'],
            ]);
            if (isset($fdSession['__chain'])) {
                $chain = \swoole_serialize::unpack($fdSession['__chain']);
                $vars = \swoole_serialize::unpack($fdSession['__vars']);

                $chain = new Chain($chain['handler'] . 'Close', [], $vars);
                try {
                    $chain->execute([$this->server, $fd, $reactorId], []);
                } catch (Exception\ChainValidException $ex) {
                }
            }
        }

        $this->server->fdSessionHandler()->destroy($fd);
    }

    public function onTask(Server $server, $taskId, $workerId, $data)
    {
        $chain = $this->newChain($data);
        $response = $chain->execute([$server, $taskId, $workerId], $this->middlewares);
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
        $chain = $this->newChain($data);
        $response = $chain->execute([$server, $taskId, $workerId], $this->middlewares);
        if ($response) {
            return $response;
        }
    }

    public function onWorkerStart(Server $server, $workerId)
    {
        $dispatcher = $this;
        $server = $this->server;
        if ($this->server->taskworker) {
            foreach ($this->server->configure('cabal.tasks', [
                'usr/tasks.php',
            ]) as $taskPath) {
                $taskPath = $this->server->rootPath($taskPath);
                if (file_exists($taskPath)) {
                    require $taskPath;
                }
            }

        } else {
            $this->route = new RouteCollection();
            $route = $this->route;
            foreach ($this->server->configure('cabal.routes', [
                'usr/routes.php',
            ]) as $routePath) {
                $routePath = $this->server->rootPath($routePath);
                if (file_exists($routePath)) {
                    require $routePath;
                } else {
                    throw new \Exception("route file '{$routePath}' not exists");;
                }
            }
            if ($this->server->debug() && $this->server->configure('cabal.document.enabled', true)) {
                $route->group([
                    'namespace' => 'Cabal\Core\Base',
                ], function ($route) {
                    $route->get('/__docs', 'DocumentController@getIndex');
                    $route->get('/__docs/{filename}.md', 'DocumentController@getMarkdown');
                });
            }
        }
    }

    public function onHandShake($swooleRequest, $swooleResponse)
    {
        $request = $this->newRequest($swooleRequest, 'WS');
        $request = $request->withAttribute('fd', $swooleRequest->fd);
        list($code, $chain, $vars) = $this->route->dispatch($request);

        $fdSession = new Session($this->server->fdSessionHandler(), $swooleRequest->fd, [
            'filter' => ['__chain', '__vars'],
        ]);
        $fdSession['__chain'] = \swoole_serialize::pack($chain);
        $fdSession['__vars'] = \swoole_serialize::pack($vars);
        $request = $request->withAttribute('fdSession', $fdSession);

        switch ($code) {
            case Route::METHOD_NOT_ALLOWED:
                $swooleResponse->status(405);
                $swooleResponse->end();
                return false;
                break;
            case Route::FOUND:
                $chain = new Chain($chain['handler'] . 'HandShake', $chain['middleware'], $vars);
                try {
                    $response = $chain->execute([$this->server, $request], $this->middlewares, function ($response) use ($swooleRequest) {
                        if ($response === false) {
                            return false;
                        } elseif ($response instanceof Headers) {
                            $response = $this->websocketResponse($swooleRequest, new Response(), $response);
                        } elseif ($response === true || $response === null) {
                            $response = $this->websocketResponse($swooleRequest, new Response());
                        } elseif (!($response instanceof ResponseInterface)) {
                            throw new \UnexpectedValueException(sprintf(
                                'response "%s"; must be bool,null,Headers or Response',
                                gettype($response)
                            ));
                        }
                        return $response;
                    });
                } catch (Exception\ChainValidException $ex) {
                    $response = true;
                }

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
                $swooleResponse->status($response->getStatusCode());
                $swooleResponse->end();
                if ($response->getStatusCode() === 101) {
                    $fdSession->write();
                    $this->server->defer(function () use ($swooleRequest) {
                        $this->onOpen($this->server, $swooleRequest);
                    });
                    return true;
                } else {
                    return false;
                }
                break;
            default:
                // Route::NOT_FOUND
                $swooleResponse->status(404);
                $swooleResponse->end();
                return false;
                break;
        }
    }

    public function onOpen($server, $swooleRequest)
    {
        $request = $this->newRequest($swooleRequest, 'WS');
        $request = $request->withAttribute('fd', $swooleRequest->fd);
        $fdSession = new Session($this->server->fdSessionHandler(), $request->fd(), [
            'filter' => ['__chain', '__vars'],
        ]);
        $chain = \swoole_serialize::unpack($fdSession['__chain']);
        $vars = \swoole_serialize::unpack($fdSession['__vars']);

        $chain = new Chain($chain['handler'] . 'Open', [], $vars);
        try {
            $chain->execute([$this->server, $request], []);
            $fdSession->write();
        } catch (Exception\ChainValidException $ex) {
        }
    }

    public function onMessage($server, $frame)
    {
        $fdSession = new Session($this->server->fdSessionHandler(), $frame->fd, [
            'filter' => ['__chain', '__vars'],
        ]);
        $chain = \swoole_serialize::unpack($fdSession['__chain']);
        $vars = \swoole_serialize::unpack($fdSession['__vars']);

        $frame = new Frame($frame, $fdSession);

        $chain = new Chain($chain['handler'] . 'Message', [], $vars);
        $chain->execute([$this->server, $frame], []);
        $fdSession->write();
    }

    public function onRequest($swooleRequest, $swooleResponse)
    {
        $request = $this->newRequest($swooleRequest);
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
                    $response = $chain->execute([$this->server, $request], $this->middlewares, [$this, 'response']);
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
     * @return \Cabal\Route\RouteCollection
     */
    public function getRoute()
    {
        return $this->route;
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
        return Response::make('<html><head><title>404 Not Found</title></head><body bgcolor="white"><h1>404 Not Found</h1></body></html>', 404);
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
        return Response::make('<html><head><title>405 Method Not Allowed</title></head><body bgcolor="white"><h1>405 Method Not Allowed</h1></body></html>', 405);
    }

    protected function handlerException(\Exception $ex, $chain, $request)
    {
        if ($this->exceptionChain) {
            return $this->exceptionChain->execute(
                [$this->server, $ex, $chain, $request],
                $this->middlewares
            );
        }
        if ($ex instanceof BadRequestException) {
            $body = '';
            $body = '<ul><li>' . implode("</li><li>", $ex->getMessages()) . '</li></ul>';
            return Response::make('<html><head><title>400 Bad Request</title></head><body bgcolor="white"><h1>400 Bad Request</h1>' . $body . '</body></html>', 400);
        }
        $body = '';
        if ($this->server->debug()) {
            $body = '<pre>' . $ex->__toString() . '</pre>';
        }
        return Response::make('<html><head><title>500 Internal Server Error</title></head><body bgcolor="white"><h1>500 Internal Server Error</h1>' . $body . '</body></html>', 500);
    }

    /**
     * Undocumented function
     *
     * @param [type] $response
     * @param boolean $websocket
     * @return \Zend\Diactoros\Response
     */
    public function response($response)
    {
        if (!($response instanceof ResponseInterface)) {
            if (is_array($response) || $response instanceof \stdClass || $response instanceof \JsonSerializable) {
                $response = Response::make(json_encode($response));
                $response = $response->withHeader('Content-Type', 'application/json');
            } elseif (is_object($response) && method_exists($response, 'render')) {
                $response = Response::make($realResponse->render());
            } elseif (is_string($response) || is_numeric($response)) {
                $response = Response::make($response);
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid response "%s"; must be an string or object has render method',
                    gettype($response)
                ));
            }
        }
        return $response;
    }

    /**
     * Undocumented function
     *
     * @param [type] $swooleRequest
     * @param [type] $response
     * @return \Zend\Diactoros\Response
     */
    protected function websocketResponse($swooleRequest, $response, $headers = [])
    {
        $secWebSocketKey = $swooleRequest->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
                // $swooleResponse->end();
            return false;
        }
            // echo $swooleRequest->header['sec-websocket-key'];
        $key = base64_encode(sha1(
            $swooleRequest->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));
        $headers['Upgrade'] = 'websocket';
        $headers['Connection'] = 'Upgrade';
        $headers['Sec-WebSocket-Accept'] = $key;
        $headers['Sec-WebSocket-Version'] = '13';
        if (isset($swooleRequest->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $swooleRequest->header['sec-websocket-protocol'];
        }
        foreach ($headers as $key => $val) {
            $response = $response->withHeader($key, $val);
        }
        $response = $response->withStatus(101);
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

    protected function newRequest($swooleRequest, $method = null)
    {
        $scheme = strtolower(current(explode('/', $swooleRequest->server['server_protocol'])));
        $fullUri = implode('', [$scheme, '://', $swooleRequest->header['host'], $swooleRequest->server['request_uri']]);
        $method = $method ? : $swooleRequest->server['request_method'];
        $fp = fopen('php://memory', 'rw');
        if ($swooleRequest->rawContent()) {
            fwrite($fp, $swooleRequest->rawContent());
        }
        $request = new Request(
            $swooleRequest->server,
            $swooleRequest->files ? : [],
            $fullUri,
            $method,
            $fp,
            $swooleRequest->header ? : [],
            $swooleRequest->cookie ? : [],
            $swooleRequest->get ? : [],
            $swooleRequest->post ? : [],
            str_replace('HTTP/', '', $swooleRequest->server['server_protocol'])
        );
        return $request;
    }
}