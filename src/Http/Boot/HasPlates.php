<?php
namespace Cabal\Core\Http\Boot;

trait HasPlates
{
    protected $viewEngine;

    public function plates()
    {
        if (!$this->viewEngine) {
            $this->viewEngine = new \League\Plates\Engine($this->rootPath('var/template'));
        }
        return $this->viewEngine;
    }
}