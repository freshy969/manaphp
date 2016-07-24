<?php

defined('UNIT_TESTS_ROOT') || require __DIR__ . '/bootstrap.php';

class CacheAdapterRedisTest extends TestCase
{
    protected $_di;
    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->_di = new \ManaPHP\Di\FactoryDefault();
        $this->_di->setShared('redis', function () {
            $redis = new Redis();
            $redis->connect('localhost');

            return $redis;
        });
    }

    public function test_exists()
    {
        $cache = new \ManaPHP\Cache\Adapter\Redis();

        $cache->_delete('var');
        $this->assertFalse($cache->_exists('var'));
        $cache->_set('var', 'value', 1000);
        $this->assertTrue($cache->_exists('var'));
    }

    public function test_get()
    {
        $cache = new \ManaPHP\Cache\Adapter\Redis();

        $cache->_delete('var');

        $this->assertFalse($cache->_get('var'));
        $cache->_set('var', 'value', 100);
        $this->assertSame('value', $cache->_get('var'));
    }

    public function test_set()
    {
        $cache = new \ManaPHP\Cache\Adapter\Redis();

        $cache->_set('var', '', 100);
        $this->assertSame('', $cache->_get('var'));

        $cache->_set('var', 'value', 100);
        $this->assertSame('value', $cache->_get('var'));

        $cache->_set('var', '{}', 100);
        $this->assertSame('{}', $cache->_get('var'));

        // ttl
        $cache->_set('var', 'value', 1);
        $this->assertTrue($cache->_exists('var'));
        sleep(2);
        $this->assertFalse($cache->_exists('var'));
    }

    public function test_delete()
    {
        $cache = new \ManaPHP\Cache\Adapter\Redis();

        //exists and delete
        $cache->_set('var', 'value', 100);
        $cache->_delete('var');

        // missing and delete
        $cache->_delete('var');
    }
}