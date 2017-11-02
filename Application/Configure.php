<?php

namespace Application;

use ManaPHP\Db\Adapter\Mysql;
use ManaPHP\Redis;

class Configure extends \ManaPHP\Configure
{
    public function __construct()
    {
        parent::__construct();

        $this->config();
    }

    public function config()
    {
        $this->timezone = 'PRC';

        $this->debug = true;

        /*
         * --------------------------------------------------------------------------
         *  Encryption Key
         * --------------------------------------------------------------------------
         *
         *  This key should be set to a random, 32 character string, otherwise these encrypted strings
         *  will not be safe. Please do this before deploying an application!
         *
         */
        $this->crypt->setMasterKey('key');

        //https://raw.githubusercontent.com/manaphp/download/master/manaphp_unit_test_db.sql
        $this->components['db'] = ['class' => Mysql::class, 'mysql://root@localhost/manaphp_unit_test?charset=utf8'];
        $this->components['redis'] = ['class' => Redis::class, 'redis://localhost:6379/1/test?timeout=2&retry_interval=0&auth='];

        $this->modules = ['Home' => '/', 'Admin' => '/admin', 'Api' => '/api'];
    }
}