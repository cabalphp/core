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
                    $this->loadeds[$file] = $this->merge($this->loadeds[$file], $array);
                }
            }
        }
        return $this->loadeds[$file];
    }

    protected function merge($arr1, $arr2)
    {
        foreach ($arr1 as $key => $val) {
            if (array_key_exists($key, $arr2)) {
                if (is_array($arr2[$key])) {
                    $arr1[$key] = $this->merge($arr1[$key], $arr2[$key]);
                } else {
                    $arr1[$key] = $arr2[$key];
                }
                unset($arr2[$key]);
            }
        }
        foreach ($arr2 as $key => $val) {
            $arr1[$key] = $arr2[$key];
        }
        return $arr1;
    }

    public function get($name, $default = null)
    {
        $name = explode('.', $name);
        $file = array_shift($name);
        return array_get($this->load($file), implode('.', $name), $default);
    }

}