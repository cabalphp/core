<?php
namespace Cabal\Core\Server;

use Cabal\Core\Server;

abstract class Protocol
{
    public function protocolSettings()
    {
        return [];
    }
    public function onReceive(Server $server, $fd, $reactorId, $data)
    {
    }

    public function onPacket(Server $server, $data, $clientInfo)
    {
    }

    public function onConnect(Server $server, $fd, $reactorId)
    {
    }

    public function onClose(Server $server, $fd, $reactorId)
    {
    }

    public function onBufferFull(Server $server, $fd)
    {
    }

    public function onBufferEmpty(Server $server, $fd)
    {
    }

}