<?php
namespace Cabal\Core;

class Config
{
    protected $dir;
    protected $env;

    public function __construct($dir, $env = 'prod')
    {
        $this->dir = $dir;
        $this->env = $env;
    }

    protected function load($file)
    {
        if (!isset($this->loadeds[$file])) {
            $this->loadeds[$file] = [];
            foreach (['', "{$this->env}/"] as $subFolder) {
                $filePath = $this->dir . "/{$subFolder}" . $file . '.php';
                if (file_exists($filePath)) {
                    $array = (array)require $filePath;
                    $this->loadeds[$file] = array_merge($this->loadeds[$file], $array);
                }
            }
        }
        return $this->loadeds[$file];
    }

    public function get($name, $default = null)
    {
        $name = explode('.', $name);
        $file = array_shift($name);
        return array_get($this->load($file), implode('.', $name), $default);
    }

}