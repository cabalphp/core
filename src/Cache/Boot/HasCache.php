<?php
namespace Cabal\Core\Cache\Boot;

use Cabal\Core\Cache\Facade;

trait HasCache
{
    protected $cache;

    /**
     * Undocumented function
     *
     * @return \Cabal\Core\Cache\Repository
     */
    public function cache()
    {
        if (!$this->cache) {
            $this->cache = new Facade($this->configure('cabal.cache'));
        }
        return $this->cache;
    }
}