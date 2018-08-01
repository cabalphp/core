<?php
namespace Cabal\Core\Logger;

use Monolog\Handler\AbstractProcessingHandler;


class CoroutineHandler extends AbstractProcessingHandler
{
    protected $path;

    public function __construct($path, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->path = $path;
    }

    protected function write(array $record)
    {
        if (\Swoole\Coroutine::getuid() >= 0) {
            \Swoole\Coroutine::writeFile($this->path, (string)$record['formatted'], FILE_APPEND);
        } else {
            file_put_contents($this->path, (string)$record['formatted'], FILE_APPEND);
        }
    }
}