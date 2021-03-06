<?php
namespace Cabal\Core\Cache;

trait ServerHasCache
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
            $this->cache = new Manager($this->configure('cache'), $this->taskworker);
        }
        return $this->cache;
    }
}