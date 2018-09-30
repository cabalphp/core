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
use Cabal\Core\Logger\CoroutineHandler;


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

    protected $started = false;

    protected $extends = [];

    /**
     * Undocumented variable
     *
     * @var \Cabal\Route\RouteCollection
     */
    protected $route;

    public function __construct()
    {
        $this->setErrorHandler();
    }

    protected function setErrorHandler()
    {
        set_error_handler(function ($level, $message, $file, $line) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }, E_ALL);
    }

    public function setServer(Server $server)
    {
        $this->server = $server;


        $server->on('start', [$this, 'onStart']);
        $server->on("workerStart", [$this, 'onWorkerStart']);
        $server->on('request', [$this, 'onRequest']);
        $server->on('message', [$this, 'onMessage']);
        $server->on('handShake', [$this, 'onHandShake']);
        $server->on('task', [$this, 'onTask']);
        $server->on('finish', [$this, 'onFinish']);

        $server->on('connect', [$this, 'onConnect']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('packet', [$this, 'onPacket']);
        // $server->on('bufferFull', [$this, 'onBufferFull']);
        // $server->on('bufferEmpty', [$this, 'onBufferEmpty']);

        $server->on('close', [$this, 'onClose']);

        return $this;
    }

    public function onStart(Server $server)
    {
        echo "Server@" . $this->server->env() . " is started at http://{$server->host}:{$server->port}\r\n";
    }

    public function onConnect(Server $server, $fd, $reactorId)
    {
        $serverPort = $server->connections[$fd]['server_port'];
        if (isset($this->extends[$serverPort])) {
            $this->getExtendServer($serverPort)->onConnect($server, $fd, $reactorId);
        }
    }

    public function onReceive(Server $server, $fd, $reactorId, $data)
    {
        $handler = $this->getExtendServer($server->connections[$fd]['server_port']);
        $handler->onReceive($server, $fd, $reactorId, $data);
    }

    public function onPacket(Server $server, $data, $clientInfo)
    {
        $handler = $this->getExtendServer($server->connections[$fd]['server_port']);
        $handler->onPacket($server, $data, $clientInfo);
    }

    public function onClose(Server $server, $fd, $reactorId)
    {
        $connectionInfo = $server->connection_info($fd);
        if (isset($this->extends[$connectionInfo['server_port']])) {
            $this->getExtendServer($connectionInfo['server_port'])->onClose($server, $fd, $reactorId);
        } elseif (isset($connectionInfo['websocket_status'])) {
            $fdSession = $this->server->fdSession($fd);
            if (isset($fdSession['__chain'])) {
                $chain = \swoole_serialize::unpack($fdSession['__chain']);
                $vars = \swoole_serialize::unpack($fdSession['__vars']);

                $chain = new Chain($chain['handler'] . 'Close', [], $vars);
                $chain->execute([$this->server, $fd, $reactorId, $fdSession], []);
                $this->server->destroyFdSession($fd);
            }
        }
    }

    public function onTask(Server $server, $taskId, $workerId, $data)
    {
        $chain = $this->newChain($data);
        try {
            $response = $chain->execute([$server, $taskId, $workerId], $this->middlewares);
        } catch (\Exception $ex) {
            $response = $this->handleTaskException($ex, $taskId, $workerId);
        }
        if ($response) {
            return $response;
        }
    }

    public function onFinish(Server $server, $taskId, $data)
    {
        $chain = $this->newChain($data);
        try {
            $chain->execute([$server, $taskId], $this->middlewares);
        } catch (\Exception $ex) {
            Logger::error($ex->__toString());
        }
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
        $this->started = true;
        $this->initLogger($server);

        $initPath = $this->server->rootPath('usr/init.php');
        if (file_exists($initPath)) {
            $server = $this->server;
            require $initPath;
        }

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
                    throw new \Exception("route file '{$routePath}' not exists");
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

    protected function initLogger(Server $server)
    {
        Logger::instance()->pushHandler(
            new CoroutineHandler(
                $server->configure('cabal.logFile', $this->server->rootPath('var/log/cabal.log')),
                $server->configure('cabal.logLevel', \Monolog\Logger::DEBUG)
            )
        );
    }

    public function onHandShake($swooleRequest, $swooleResponse)
    {
        $request = $this->newRequest($swooleRequest, 'WS');
        $request = $request->withAttribute('fd', $swooleRequest->fd);
        list($code, $chain, $vars) = $this->route->dispatch($request);

        $fdSession = $this->server->fdSession($swooleRequest->fd);
        $fdSession['__chain'] = \swoole_serialize::pack($ƒ);
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
        $fdSession = $this->server->fdSession($request->fd());

        $chain = \swoole_serialize::unpack($fdSession['__chain']);
        $vars = \swoole_serialize::unpack($fdSession['__vars']);

        $chain = new Chain($chain['handler'] . 'Open', [], $vars);
        $chain->execute([$this->server, $request], []);
        $fdSession->write();
    }

    public function onMessage($server, $frame)
    {
        $fdSession = $this->server->fdSession($frame->fd);
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
                    $response = $this->handleRequestException($ex, $chain, $request);
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

    public function handleException($ex)
    {
        if ($this->exceptionChain) {
            return $this->exceptionChain->execute(
                [$this->server, $ex],
                $this->middlewares
            );
        }
        Logger::error($ex->__toString());
        return false;
    }

    protected function handleTaskException(\Exception $ex, $taskId, $workerId)
    {
        if ($this->exceptionChain) {
            return $this->exceptionChain->execute(
                [$this->server, $ex, $taskId, $workerId],
                $this->middlewares
            );
        }
        Logger::error($ex->__toString(), [
            'taskId' => $taskId,
            'workerId' => $workerId,
        ]);
    }

    protected function handleRequestException(\Exception $ex, $chain, $request)
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
        Logger::error($ex->__toString());
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
            } elseif (is_string($response) || is_numeric($response) || is_bool($response)) {
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
            return false;
        }
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

    public function setExtendServer($port, $handlerClass)
    {
        $this->extends[$port] = $handlerClass;
    }
    /**
     * Undocumented function
     *
     * @param [type] $port
     * @return \Cabal\Core\Server\Protocol
     */
    public function getExtendServer($port)
    {
        return $this->extends[$port];
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
        if (!$this->started) {
            throw new \Exception("Please register exception handler after worker start!");
        }
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
        var_dump($swooleRequest->header);
        if (!isset($swooleRequest->header['host'])) {
            Logger::debug('Header lost header field', [$swooleRequest->header]);
        }
        $fullUri = implode('', [$scheme, '://', $swooleRequest->header['host'], $swooleRequest->server['request_uri']]);
        $method = $method ? : $swooleRequest->server['request_method'];
        $fp = fopen('php://memory', 'rw');
        if ($swooleRequest->rawContent()) {
            fwrite($fp, $swooleRequest->rawContent());
        }
        $postData = $swooleRequest->post;
        if (isset($swooleRequest->header['content-type']) && strpos($swooleRequest->header['content-type'], 'json') !== false) {
            $postData = json_decode($swooleRequest->rawContent(), true);
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
            $postData ? : [],
            str_replace('HTTP/', '', $swooleRequest->server['server_protocol'])
        );
        return $request;
    }
}