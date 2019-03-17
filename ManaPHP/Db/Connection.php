<?php
namespace ManaPHP\Db;

use ManaPHP\Component;
use ManaPHP\Db\Exception as DbException;
use ManaPHP\Exception\InvalidValueException;
use ManaPHP\Exception\NotSupportedException;

class Connection extends Component
{
    /**
     * @var string
     */
    protected $_dsn;

    /**
     * @var string
     */
    protected $_username;

    /**
     * @var string
     */
    protected $_password;

    /**
     * @var array
     */
    protected $_options = [];

    /**
     * @var \PDO
     */
    protected $_pdo;


    /**
     * Current transaction level
     *
     * @var int
     */
    protected $_transactionLevel = 0;

    /**
     * @var \PDOStatement[]
     */
    protected $_prepared = [];

    /**
     * @var int
     */
    protected $_ping_interval = 60;

    /**
     * @var float
     */
    protected $_last_io_time;

    public function __construct($dsn, $username, $password, $options)
    {
        $this->_dsn = $dsn;
        $this->_username = $username;
        $this->_password = $password;
        $this->_options = $options;
    }

    /**
     * @return bool
     */
    protected function _ping()
    {
        try {
            @$this->_pdo->query("SELECT 'PING'")->fetchAll();
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * @return \PDO
     */
    protected function _getPdo()
    {
        if ($this->_pdo === null) {
            $this->logger->debug(['connect to `:dsn`', 'dsn' => $this->_dsn], 'db.connect');
            $this->eventsManager->fireEvent('db:beforeConnect', $this, ['dsn' => $this->_dsn]);
            try {
                $this->_pdo = @new \PDO($this->_dsn, $this->_username, $this->_password, $this->_options);
            } catch (\PDOException $e) {
                throw new ConnectionException(['connect `:dsn` failed: :message', 'message' => $e->getMessage(), 'dsn' => $this->_dsn], $e->getCode(), $e);
            }
            $this->eventsManager->fireEvent('db:afterConnect', $this);

            if (!isset($this->_options[\PDO::ATTR_PERSISTENT]) || !$this->_options[\PDO::ATTR_PERSISTENT]) {
                $this->_last_io_time = microtime(true);
                return $this->_pdo;
            }
        }

        if ($this->_transactionLevel === 0 && microtime(true) - $this->_last_io_time >= $this->_ping_interval && !$this->_ping()) {
            $this->close();
            $this->logger->info(['reconnect to `:dsn`', 'dsn' => $this->_dsn], 'db.reconnect');
            $this->eventsManager->fireEvent('db:reconnect', $this, ['dsn' => $this->_dsn]);
            $this->eventsManager->fireEvent('db:beforeConnect', $this, ['dsn' => $this->_dsn]);
            try {
                $this->_pdo = @new \PDO($this->_dsn, $this->_username, $this->_password, $this->_options);
            } catch (\PDOException $e) {
                throw new ConnectionException(['connect `:dsn` failed: :message', 'message' => $e->getMessage(), 'dsn' => $this->_dsn], $e->getCode(), $e);
            }
            $this->eventsManager->fireEvent('db:afterConnect', $this);
        }

        $this->_last_io_time = microtime(true);

        return $this->_pdo;
    }

    /**
     * @param string $sql
     * @param array  $bind
     *
     * @return \PDOStatement
     */
    public function _execute($sql, $bind)
    {
        if (!isset($this->_prepared[$sql])) {
            if (count($this->_prepared) > 8) {
                array_shift($this->_prepared);
            }
            $this->_prepared[$sql] = @$this->_getPdo()->prepare($sql);
        }
        $statement = $this->_prepared[$sql];

        foreach ($bind as $parameter => $value) {
            if (is_string($value)) {
                $type = \PDO::PARAM_STR;
            } elseif (is_int($value)) {
                $type = \PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = \PDO::PARAM_BOOL;
            } elseif ($value === null) {
                $type = \PDO::PARAM_NULL;
            } elseif (is_float($value)) {
                $type = \PDO::PARAM_STR;
            } elseif (is_array($value) || $value instanceof \JsonSerializable) {
                $type = \PDO::PARAM_STR;
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                throw new NotSupportedException(['The `:type` type of `:parameter` parameter is not support', 'parameter' => $parameter, 'type' => gettype($value)]);
            }

            if (is_int($parameter)) {
                $statement->bindValue($parameter + 1, $value, $type);
            } else {
                if ($parameter[0] === ':') {
                    throw new InvalidValueException(['Bind does not require started with `:` for `:parameter` parameter', 'parameter' => $parameter]);
                }

                $statement->bindValue(':' . $parameter, $value, $type);
            }
        }

        @$statement->execute();

        return $statement;
    }


    /**
     * @param string $sql
     * @param array  $bind
     * @param bool   $has_insert_id
     *
     * @return int
     * @throws \ManaPHP\Db\ConnectionException
     * @throws \ManaPHP\Exception\InvalidValueException
     * @throws \ManaPHP\Exception\NotSupportedException
     */
    public function execute($sql, $bind, $has_insert_id = false)
    {
        $r = $bind ? $this->_execute($sql, $bind)->rowCount() : @$this->_getPdo()->exec($sql);
        if ($has_insert_id) {
            return $this->_getPdo()->lastInsertId();
        } else {
            return $r;
        }
    }

    /**
     * @param string $sql
     * @param array  $bind
     * @param int    $fetchMode
     * @param bool   $useMaster
     *
     * @return array
     */
    public function query($sql, $bind, $fetchMode, $useMaster = false)
    {
        try {
            $statement = $bind ? $this->_execute($sql, $bind) : @$this->_getPdo()->query($sql);
        } catch (\PDOException $e) {
            $failed = true;

            if ($this->_transactionLevel === 0 && !$this->_ping()) {
                try {
                    $this->close();
                    $statement = $bind ? $this->_execute($sql, $bind) : @$this->_getPdo()->query($sql);
                    $failed = false;
                } catch (\PDOException $e) {

                }
            }

            if ($failed) {
                throw new DbException([
                    ':message => ' . PHP_EOL . 'SQL: ":sql"' . PHP_EOL . ' BIND: :bind',
                    'message' => $e->getMessage(),
                    'sql' => $sql,
                    'bind' => json_encode($bind, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                ], 0, $e);
            }
        }

        return $statement->fetchAll($fetchMode);
    }

    public function close()
    {
        if ($this->_pdo) {
            $this->_pdo = null;
            $this->_prepared = [];
            $this->_last_io_time = null;
            if ($this->_transactionLevel !== 0) {
                $this->_transactionLevel = 0;
                $this->_pdo->rollBack();
                $this->logger->warn('transaction is not close correctly', 'db.transaction.abnormal');
            }
        }
    }

    public function beginTransaction()
    {
        return $this->_getPdo()->beginTransaction();
    }

    public function rollBack()
    {
        return $this->_getPdo()->rollBack();
    }

    public function commit()
    {
        return $this->_getPdo()->rollBack();
    }
}