<?php
namespace Cabal\Core\Http\Server;

trait HasTwig
{
    protected $twig;

    public function twig()
    {
        if (!$this->twig) {
            $loader = new \Twig\Loader\FilesystemLoader($this->rootPath('var/template'));
            $this->twig = new \Twig\Environment($loader);
        }
        return $this->twig;
    }
}
