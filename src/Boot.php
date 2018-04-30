<?php
namespace Cabal\Core;

use Cabal\Core\Server;

class Boot
{
    /**
     * Undocumented function
     *
     * @param string $root root
     * @param string $env env
     */
    public function __construct()
    {

    }

    /**
     * Undocumented function
     *
     * @param [type] $root
     * @param string $env
     * @return \Server|\Cabal\Core\Server
     */
    public function createServer($root, $env = 'prod', $serverClass = '')
    {
        $serverClass = $serverClass ? : Server::class;
        $server = new $serverClass($root, $env);
        return $server;
    }



}