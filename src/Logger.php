<?php
namespace Cabal\Core;

use Monolog\Logger as MonologLogger;


class Logger
{
    static $instances;

    /**
     * Undocumented function
     *
     * @param string $name
     * @return \Monolog\Logger
     */
    static public function instance($name = 'default')
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new MonologLogger($name);
        }
        return self::$instances[$name];
    }

    static public function log($level, $message, array $context = array())
    {
        return self::add($level, $message, $context);
    }

    static public function add($level, $message, array $context = array())
    {
        self::instance()->addRecord($level, $message, $context);
    }

    static public function debug($message, array $context = array())
    {
        self::instance()->addDebug($message, $context);
    }

    static public function info($message, array $context = array())
    {
        self::instance()->addInfo($message, $context);
    }

    static public function notice($message, array $context = array())
    {
        self::instance()->addNotice($message, $context);
    }

    static public function warning($message, array $context = array())
    {
        self::instance()->addWarning($message, $context);
    }

    static public function error($message, array $context = array())
    {
        self::instance()->addError($message, $context);
    }

    static public function critical($message, array $context = array())
    {
        self::instance()->addCritical($message, $context);
    }

    static public function alert($message, array $context = array())
    {
        self::instance()->addAlert($message, $context);
    }

    static public function emergency($message, array $context = array())
    {
        self::instance()->addEmergency($message, $context);
    }
}