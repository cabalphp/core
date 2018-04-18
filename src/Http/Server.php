<?php
namespace Cabal\Core\Http;

use Cabal\Core\Application\Boot;


class Server extends \Swoole\Http\Server
{
    protected $debug = false;
    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\Application\Boot
     */
    protected $boot;

    public function __construct(Boot $boot, $host, $port, $mode = SWOOLE_PROCESS, $sockType = SWOOLE_SOCK_TCP)
    {
        $this->boot = $boot;
        parent::__construct($host, $port, $mode, $sockType);
    }

    /**
     * Undocumented function
     *
     * @return \Boot|\Cabal\Core\Application\Boot
     */
    public function boot()
    {
        return $this->boot;
    }

    public function debug($isDebug = null)
    {
        if ($isDebug !== null) {
            $this->debug = $isDebug;
        }
        return $this->debug;
    }


}