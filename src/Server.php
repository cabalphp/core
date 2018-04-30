<?php
namespace Cabal\Core;


class Server extends \Swoole\Http\Server
{
    protected $root;

    protected $debug = false;

    protected $env;

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
        $this->debug = $this->config->get('cabal.debug', false);

        $host = $this->config->get('cabal.host', '127.0.0.1');
        $port = $this->config->get('cabal.port', 9501);
        $mode = $this->config->get('cabal.mode', SWOOLE_PROCESS);
        $sockType = $this->config->get('cabal.sockType', SWOOLE_SOCK_TCP);

        parent::__construct($host, $port, $mode, $sockType);

        $this->addListener($host, $port + 1, SWOOLE_SOCK_TCP);
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

        $this->set($swooleSettings);

        $this->on('start', [$this->dispatcher, 'onStart']);
        $this->on("workerStart", [$this->dispatcher, 'onWorkerStart']);
        $this->on('request', [$this->dispatcher, 'onRequest']);
        $this->on('task', [$this->dispatcher, 'onTask']);
        $this->on('finish', [$this->dispatcher, 'onFinish']);

        $this->dispatcher->setServer($this);

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