<?php
namespace Cabal\Core\Exception;

class BadRequestException extends \Exception
{
    protected $messages;

    public function __construct($messages, $code = 0)
    {
        $this->messages = $messages;
        parent::__construct($messages[0]);
    }

    public function getMessages()
    {
        return $this->messages;
    }
}