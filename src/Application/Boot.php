<?php
namespace Cabal\Core\Application;

use Cabal\Core\Http\Server;

class Boot
{

    protected $root;

    protected $env;

    /**
     * @var \Cabal\Core\Application\Config
     */
    protected $config;

    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\Application\Dispatcher
     */
    protected $dispatcher;


    /**
     * Undocumented function
     *
     * @param string $root root
     * @param string $env env
     */
    public function __construct($root, $env = 'prod')
    {
        $this->root = $root;
        $this->env = $env ? : 'prod';
        $this->config = new Config($this->root . '/conf', $this->env);

        $this->dispatcher = new Dispatcher();
    }

    protected function initServer()
    {
        $host = $this->config->get('cabal.host', '127.0.0.1');
        $port = $this->config->get('cabal.port', 9501);
        $mode = $this->config->get('cabal.mode', SWOOLE_PROCESS);
        $sockType = $this->config->get('cabal.sockType', SWOOLE_SOCK_TCP);
        $server = new Server($this, $host, $port, $mode, $sockType);

        $server->debug($this->config->get('cabal.debug', false));

        $swooleSettings = (array)$this->config->get('cabal.swoole', []);
        $swooleSettings = array_merge([
            'daemonize' => true,
            'log_file' => $this->rootPath('var/log/cabal.log'),
            'pid_file' => $this->rootPath('var/cabal.pid'),
            'reload_async' => true,
            'request_slowlog_timeout' => 1,
            'request_slowlog_file' => $this->rootPath('var/trace.log'),
            'trace_event_worker' => true,

        ], $swooleSettings);

        $server->set($swooleSettings);

        $server->on('start', [$this->dispatcher, 'onStart']);

        $server->on("workerStart", [$this->dispatcher, 'onWorkerStart']);

        $server->on('request', [$this->dispatcher, 'onRequest']);

        $this->dispatcher->setServer($server);

        return $server;
    }

    /**
     * Undocumented function
     *
     * @return \Cabal\Core\Application\Dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function env()
    {
        return $this->env;
    }

    public function start()
    {
        return $this->initServer()->start();
    }

    public function rootPath($path)
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path);
    }

    public function configure($name, $default = null)
    {
        return $this->config->get($name, $default);
    }

    public function version()
    {
        return '0.0.1-α';
    }
}