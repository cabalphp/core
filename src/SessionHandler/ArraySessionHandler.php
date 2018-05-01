<?php

namespace Cabal\Core\SessionHandler;

use Cabal\Core\Cache\RepositoryInterface;


class ArraySessionHandler extends AbstractSessionHandler
{
    protected $sessions = [];

    public function __construct()
    {
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        return isset($this->sessions[$id]) ? $this->sessions[$id] : '';
    }

    /**
     * Undocumented function
     *
     * @param string $id
     * @param string $sessionData
     * @return void
     */
    public function write($id, $sessionData)
    {
        $this->sessions[$id] = $sessionData;
        return true;
    }

    public function destroy($id)
    {
        if (isset($this->sessions[$id])) {
            unset($this->sessions[$id]);
        }
        return true;
    }

    public function gc($maxLifetime)
    {
        return true;
    }
}