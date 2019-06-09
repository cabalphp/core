<?php
namespace Cabal\Core\Http\Server;

trait HasTwig
{
    protected $twig;

    public function twig()
    {
        if (!$this->twig) {

            $loader = new \Twig\Loader\FilesystemLoader($this->configure('twig.paths',$this->rootPath('var/template')));
            $this->twig = new \Twig\Environment($loader, $this->configure('twig.options',[]));
        }
        return $this->twig;
    }
}
