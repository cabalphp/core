<?php
namespace Cabal\Core\Http;

use Zend\Diactoros\ServerRequest;

class Frame extends ServerRequest
{
    public $fd;
    public $data;
    public $opcode;
    public $fdSession;

    public function __construct($frame, $fdSession)
    {
        $this->fd = $frame->fd;
        $this->data = $frame->data;
        $this->opcode = $frame->opcode;
        $this->fdSession = $fdSession;
    }


    /**
     * Undocumented function
     *
     * @param [type] $name
     * @param [type] $val
     * @return \Cabal\Core\Session 
     */
    public function fdSession()
    {
        switch (count(func_get_args())) {
            case 0:
                return $this->fdSession;
                break;
            case 1:
                return $this->fdSession->offsetGet($name);
                break;
            case 2:
                $this->fdSession->offsetSet($name, $val);
                return $this->fdSession;
                break;
            default:
                throw new \InvalidArgumentException(sprintf(
                    'Invalid args num "%d"',
                    count(func_get_args())
                ));
                break;
        }
    }

}