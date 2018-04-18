<?php

namespace Cabal\Core\Http\SessionHandler;

use Cabal\Core\Cache\RepositoryInterface;


class RedisSessionHandler extends AbstractSessionHandler
{

    protected $repository;
    protected $prefix;
    protected $maxLifetime;
    public function __construct(RepositoryInterface $repository, $prefix = 'sess_', $maxLifetime = 1440)
    {
        $this->repository = $repository;
        $this->prefix = $prefix;
        $this->maxLifetime = $maxLifetime;
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
        return $this->repository->get("{$this->prefix}{$id}") ? : '';
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
        $this->repository->set("{$this->prefix}{$id}", $sessionData, $this->maxLifetime / 60);
        return true;
    }

    public function destroy($id)
    {
        $this->repository->del("{$this->prefix}{$id}");
        return true;
    }

    public function gc($maxLifetime)
    {
        $this->maxLifetime = $maxLifetime;
        return true;
    }
}