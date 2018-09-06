<?php
namespace Cabal\Core;

use Cabal\Core\SessionHandler\ArraySessionHandler;

class Server extends \Swoole\WebSocket\Server
{
    protected $root;

    protected $debug = false;

    protected $env;

    protected $extends = [];

    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\SessionHandler\ArraySessionHandler 
     */
    protected $fdSessionHandler;

    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\Dispatcher
     */
    protected $dispatcher;

    public function __construct($root, $env)
    {
        $this->root = $root;
        $this->env = $env;

        $this->dispatcher = new Dispatcher();
        $this->config = new Config($this->root . '/conf', $this->env);
        $this->debug = $this->configure('cabal.debug', false);

        $host = $this->configure('cabal.host', '127.0.0.1');
        $port = $this->configure('cabal.port', 9501);
        $mode = $this->configure('cabal.mode', SWOOLE_PROCESS);
        $sockType = $this->configure('cabal.sockType', SWOOLE_SOCK_TCP);

        parent::__construct($host, $port, $mode, $sockType);
        // $this->addListener($host, $port + 1, SWOOLE_SOCK_TCP); 
        $swooleSettings = (array)$this->configure('cabal.swoole', []);
        $swooleSettings = array_merge([
            'daemonize' => false,
            'log_file' => $this->rootPath('var/log/swoole.log'),
            'pid_file' => $this->rootPath('var/cabal.pid'),
            'reload_async' => true,
            'request_slowlog_timeout' => 1,
            'request_slowlog_file' => $this->rootPath('var/trace.log'),
            'trace_event_worker' => true,

        ], $swooleSettings);

        $this->set($swooleSettings);
        $this->dispatcher->setServer($this);

    }

    public function extendServer($host, $port, $handler)
    {
        $listen = $this->listen($host, $port, SWOOLE_SOCK_TCP);
        //["{$host}:{$port}"] = $handlerClass; 
        $listen->set(['open_http_protocol' => false]);
        $listen->set($handler->protocolSettings());

        $this->dispatcher->setExtendServer($port, $handler);
    }

    public function debug($isDebug = null)
    {
        if ($isDebug !== null) {
            $this->debug = $isDebug;
        }
        return $this->debug;
    }




    /**
     * Undocumented function
     *
     * @return \Cabal\Core\SessionHandler\ArraySessionHandler
     */
    public function fdSessionHandler()
    {
        if (!$this->fdSessionHandler) {
            $this->fdSessionHandler = new ArraySessionHandler();
        }
        return $this->fdSessionHandler;
    }


    public function fdSession($fd, $config = [])
    {
        $config = array_merge([
            'filter' => ['__chain', '__vars'],
        ], $config);
        return new Session($this->fdSessionHandler(), $fd, $config);
    }

    public function destroyFdSession($fd)
    {
        $this->fdSessionHandler()->destroy($fd);
    }
    /**
     * Undocumented function
     *
     * @return \Cabal\Core\Dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function rootPath($path)
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path);
    }

    public function configure($name, $default = null)
    {
        return $this->config->get($name, $default);
    }

    public function env()
    {
        return $this->env;
    }
    public function version()
    {
        return '0.0.1-α';
    }
}