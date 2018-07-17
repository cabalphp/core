<?php
use PHPUnit\Framework\TestCase;
use Cabal\Core\Cache\Manager;

require_once __DIR__ . '/../vendor/autoload.php';

class CacheTest extends TestCase
{
    /**
     * Undocumented variable
     *
     * @var \Cabal\Core\Cache\Manager
     */
    static $cache;

    static $tempId;

    public static function setUpBeforeClass()
    {
        if (!self::$cache) {
            self::$tempId = uniqid();
            self::$cache = new Manager([
                'default' => 'test',
                'test' => [
                    'driver' => 'redis',
                    'host' => '127.0.0.1',
                    'port' => '6379',
                    'auth' => '123456',
                ],
                'repo' => [
                    'driver' => 'redis',
                    'host' => '127.0.0.1',
                    'port' => '6379',
                    'auth' => '123456',
                ],
            ], 1);
        }

    }

    public function testSet()
    {
        $result = self::$cache->set("cabal:test:simple:" . self::$tempId, self::$tempId, 3 / 60);
        $this->assertEquals($result, self::$tempId);

        $result = self::$cache->remember("cabal:test:remember:" . self::$tempId, 3 / 60, function () {
            return self::$tempId;
        });
        $this->assertEquals($result, self::$tempId);
        $result = self::$cache->set("cabal:test:forget:" . self::$tempId, self::$tempId, 60);
    }

    /**
     * @depends testSet
     */
    public function testGet()
    {
        $val = self::$cache->get("cabal:test:simple:" . self::$tempId);
        $this->assertEquals($val, self::$tempId);
        $val = self::$cache->get("cabal:test:remember:" . self::$tempId);
        $this->assertEquals($val, self::$tempId);
    }

    /**
     * @depends testGet
     */
    public function testForget()
    {
        self::$cache->forget("cabal:test:forget:" . self::$tempId);
        $val = self::$cache->get("cabal:test:forget:" . self::$tempId);
        $this->assertEquals($val, null);
    }

    /**
     * @depends testGet
     */
    public function testExpire()
    {
        sleep(3);
        $val = self::$cache->get("cabal:test:simple:" . self::$tempId);
        $this->assertEquals($val, null);
        $val = self::$cache->get("cabal:test:remember:" . self::$tempId);
        $this->assertEquals($val, null);
    }

    // ---
    public function testRepoSet()
    {
        $result = self::$cache->getRepository('repo')->set("cabal:test:repo_simple:" . self::$tempId, self::$tempId, 3 / 60);
        $this->assertEquals($result, self::$tempId);
        $result = self::$cache->getRepository('repo')->remember("cabal:test:repo_remember:" . self::$tempId, 3 / 60, function () {
            return self::$tempId;
        });
        $this->assertEquals($result, self::$tempId);
    }

    /**
     * @depends testRepoSet
     */
    public function testRepoGet()
    {
        $val = self::$cache->get("cabal:test:repo_simple:" . self::$tempId);
        $this->assertEquals($val, self::$tempId);
        $val = self::$cache->get("cabal:test:repo_remember:" . self::$tempId);
        $this->assertEquals($val, self::$tempId);
    }

    /**
     * @depends testRepoGet
     */
    public function testRepoExpire()
    {
        sleep(3);
        $val = self::$cache->get("cabal:test:repo_simple:" . self::$tempId);
        $this->assertEquals($val, null);
        $val = self::$cache->get("cabal:test:repo_remember:" . self::$tempId);
        $this->assertEquals($val, null);
    }


    public function testIncrement()
    {
        $val = self::$cache->increment("cabal:test:incr:" . self::$tempId);
        $this->assertEquals($val, 1);
        for ($i = 0; $i < mt_rand(10, 20); $i++) {
            $val = self::$cache->increment("cabal:test:incr:" . self::$tempId);
        }
        $this->assertEquals($val, $i + 1);

        for ($j = 0; $j < mt_rand(10, 20); $j++) {
            $val = self::$cache->decrement("cabal:test:incr:" . self::$tempId);
        }
        $this->assertEquals($val, $i + 1 - $j);

    }

}