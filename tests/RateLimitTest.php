<?php

namespace Touhonoob\RateLimit\Tests;

use Touhonoob\RateLimit\Adapter;
use Touhonoob\RateLimit\RateLimit;

/**
 * @author Peter Chung <touhonoob@gmail.com>
 * @date May 16, 2015
 */
class RateLimitTest extends \PHPUnit_Framework_TestCase
{

    const NAME = "RateLimitTest";
    const MAX_REQUESTS = 10;
    const PERIOD = 3;

    /**
     * @requires extension apc
     */
    public function testCheckAPC()
    {
        if (!extension_loaded('apc')) {
            $this->markTestSkipped("apc extension not installed");
        }
        if (ini_get('apc.enable_cli') == 0) {
            $this->markTestSkipped("apc.enable_cli != 1; can't change at runtime");
        }

        $adapter = new \Touhonoob\RateLimit\Adapter\APC();
        $this->check($adapter);
    }

    /**
     * @requires extension apcu
     */
    public function testCheckAPCu()
    {
        if (!extension_loaded('apcu')) {
            $this->markTestSkipped("apcu extension not installed");
        }
        if (ini_get('apc.enable_cli') == 0) {
            $this->markTestSkipped("apc.enable_cli != 1; can't change at runtime");
        }
        $adapter = new \Touhonoob\RateLimit\Adapter\APCu();
        $this->check($adapter);
    }

    /**
     * @requires extension redis
     */
    public function testCheckRedis()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped("redis extension not installed");
        }
        $redis = new \Redis();
        $redis->connect('localhost');
        $adapter = new \Touhonoob\RateLimit\Adapter\Redis($redis);
        $this->check($adapter);
    }

    public function testCheckMemcached()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped("memcached extension not installed");
        }
        $adapter = new \Touhonoob\RateLimit\Adapter\Memcached();
        $this->check($adapter);
    }




    private function check($adapter)
    {
        $label = uniqid("label", true); // should stop storage conflicts if tests are running in parallel.
        $rateLimit = $this->getRateLimit($adapter);
        $rateLimit->ttl = 100;

        $rateLimit->purge($label); // make sure a previous failed test doesn't mess up this one.

        $this->assertEquals(self::MAX_REQUESTS, $rateLimit->getAllowance($label));

        // All should work, but bucket will be empty at the end.
        for ($i = 0; $i < self::MAX_REQUESTS; $i++) {
            // Calling check reduces the counter each time.
            $this->assertEquals(self::MAX_REQUESTS - $i, $rateLimit->getAllowance($label));
            $this->assertTrue($rateLimit->check($label));
        }

        // bucket empty.
        $this->assertFalse($rateLimit->check($label), "Bucket should be empty");
        $this->assertEquals(0, $rateLimit->getAllowance($label), "Bucket should be empty");

        //Wait for PERIOD seconds, bucket should refill.
        sleep(self::PERIOD);
        $this->assertEquals(self::MAX_REQUESTS, $rateLimit->getAllowance($label));
        $this->assertTrue($rateLimit->check($label));
    }

    private function getRateLimit(Adapter $adapter)
    {
        return new RateLimit(self::NAME, self::MAX_REQUESTS, self::PERIOD, $adapter);
    }
}
