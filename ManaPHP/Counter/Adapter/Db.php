<?php
namespace ManaPHP\Counter\Adapter {

    use ManaPHP\Counter;

    /**
     *  CREATE TABLE `manaphp_counter` (
     *      `name` char(128) CHARACTER SET latin1 NOT NULL,
     *      `counter` bigint(20) NOT NULL,
     *      `created_time` int(11) NOT NULL,
     *      `updated_time` int(11) NOT NULL,
     *       PRIMARY KEY (`name`)
     *      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
     */
    class Db extends Counter
    {
        /**
         * @var string
         */
        protected $_table = 'manaphp_counter';

        /**
         * Db constructor.
         *
         * @param string|array|\ConfManaPHP\Counter\Adapter\Db $options
         */
        public function __construct($options = [])
        {
            parent::__construct();

            if (is_object($options)) {
                $options = (array)$options;
            }

            if (is_string($options)) {
                $options['table'] = $options;
            }

            if (isset($options['table'])) {
                $this->_table = $options['table'];
            }
        }

        protected function _formatKey($key)
        {
            if (is_string($key)) {
                return $key;
            } else {
                return implode('/', $key);
            }
        }

        /**
         * @param array|string $key
         *
         * @return int
         * @throws \ManaPHP\Db\Exception
         */
        public function _get($key)
        {
            $key = $this->_formatKey($key);

            $r = $this->db->fetchOne('SELECT counter' . ' FROM ' . $this->_table . ' WHERE name=:name', ['name' => $key]);
            if (!$r) {
                return 0;
            } else {
                return (int)$r['counter'];
            }
        }

        /**
         * @param string $key
         * @param int    $step
         *
         * @return int
         * @throws \ManaPHP\Counter\Adapter\Exception|\ManaPHP\Db\Exception
         */
        public function _increment($key, $step)
        {
            $key = $this->_formatKey($key);
            $time = time();

            for ($i = 0; $i < 100; $i++) {
                $r = $this->db->fetchOne('SELECT counter' . ' FROM ' . $this->_table . ' WHERE name=:name', ['name' => $key]);
                if (!$r) {
                    try {
                        $this->db->insert($this->_table, ['name' => $key, 'counter' => $step, 'created_time' => $time, 'updated_time' => $time]);
                        return $step;
                    } catch (\Exception $e) {
                        //maybe this record has been inserted by other request.
                    }

                    $r = $this->db->fetchOne('SELECT counter' . ' FROM ' . $this->_table . ' WHERE name=:name', ['name' => $key]);
                }

                $old_counter = $r['counter'];

                $sql = 'UPDATE ' . $this->_table . ' SET counter =counter+:step, updated_time =:updated_time WHERE name =:name AND counter =:old_counter';
                $r = $this->db->execute($sql, ['name' => $key, 'step' => $step, 'old_counter' => $old_counter, 'updated_time' => $time]);
                if ($r === 1) {
                    return $old_counter + $step;
                }
            }

            throw new Exception('update counter failed: ' . $key);
        }

        /**
         * @param array|string $key
         *
         * @return void
         * @throws \ManaPHP\Db\Exception
         */
        public function _delete($key)
        {
            $key = $this->_formatKey($key);

            $this->db->delete($this->_table, ['name' => $key]);
        }
    }
}