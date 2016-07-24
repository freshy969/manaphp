<?php

defined('UNIT_TESTS_ROOT') || require __DIR__ . '/bootstrap.php';

class CounterAdapterDbTest extends TestCase
{
    protected $_di;

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->_di = new ManaPHP\Di\FactoryDefault();
        $this->_di->setShared('db', function () {
            $config = require __DIR__ . '/config.database.php';
            $db = new ManaPHP\Db\Adapter\Mysql($config['mysql']);
            $db->attachEvent('db:beforeQuery', function ($event, \ManaPHP\DbInterface $source, $data) {
                //  var_dump(['sql'=>$source->getSQL(),'bind'=>$source->getBind()]);
                var_dump($source->getSQL(), $source->getEmulatedSQL(2));

            });
            $db->execute('SET GLOBAL innodb_flush_log_at_trx_commit=2');
            return $db;
        });
    }

    public function test_get()
    {
        $counter = new ManaPHP\Counter\Adapter\Db();

        $counter->delete('c1');

        $this->assertEquals(0, $counter->_get('c1'));
        $counter->increment('c1');
        $this->assertEquals(1, $counter->_get('c1'));

        $counter->delete(['c', 1]);
        $this->assertEquals(0, $counter->get(['c', 1]));

        $counter->increment(['c', 1]);
        $this->assertEquals(1, $counter->get(['c', 1]));
    }

    public function test_increment()
    {
        $counter = new ManaPHP\Counter\Adapter\Db();

        $counter->delete('c1');
        $this->assertEquals(2, $counter->_increment('c1', 2));
        $this->assertEquals(22, $counter->_increment('c1', 20));
        $this->assertEquals(2, $counter->_increment('c1', -20));
    }

    public function test_delete()
    {
        $counter = new ManaPHP\Counter\Adapter\Db();
        
        $counter->_delete('c1');

        $counter->_increment('c1', 1);
        $counter->_delete('c1');
    }
}
