<?php

namespace asinking\db;

use PDO;

abstract class DbDriver
{
    /**
     * @var PDO
     */
    private $_pdo;

    /**
     * @var \PDOStatement
     */
    private $_statement;
    /**
     * @var int
     */
    private $_retryNum = 3;

    public function __construct()
    {

    }

    /**
     * @return PDO
     */
    private function pdo($retry = 0)
    {
        if ($this->_pdo) {
            return $this->_pdo;
        }
        try {
            $config = $this->getDbConfig();
            $config['dsn'] = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8";
            $config['options'] = [PDO::ATTR_TIMEOUT => 1];
            $this->_pdo = new PDO($config['dsn'], $config['username'], $config['passwd'], $config['options']);
            $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->_pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
            $this->_pdo->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
            $this->_pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
            $this->_pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\PDOException $e) {
            if ($retry < $this->_retryNum) {
                $this->_pdo = null;
                return $this->pdo(++$retry);
            }
            throw $e;
        }
        return $this->_pdo;
    }


    /**
     * 查询参数过滤
     * @param $str
     * @return string
     */
    public function quote($str)
    {
        return $this->pdo()->quote($str);
    }

    /**
     * 获取最近插入行ID
     * @return int
     */
    public function lastInsertId()
    {
        return (int)$this->pdo()->lastInsertId();
    }

    /**
     * 获取最后一次DB错误
     * @return \Error
     */
    public function getLastError()
    {
        if (!$this->_pdo) {
            return new \Error('', 0);
        }

        $errorInfo = null;
        if ($this->_statement) {
            $errorInfo = $this->_statement->errorInfo();
        }
        if (!$errorInfo || !$errorInfo[2]) {
            $errorInfo = $this->_pdo->errorInfo();
        }

        if ($errorInfo) {
            return new \Error($errorInfo[2], $errorInfo[1]);
        } else {
            return new \Error('', 0);
        }
    }

    /*public function query($sql, $mode = DbResults::FETCH_ONE)
    {直接查询
        return $this->execute($sql, array(), $mode, false);
    }*/

    /**
     * 执行数据库操作
     * @param $sql
     * @param array $params
     * @param int $mode
     * @param bool $prepare
     * @param int $retry
     * @return array|bool|int|\PDOStatement
     * @throws \Exception
     */
    public function execute($sql, $params = array(), $mode = DbResults::AFFECTED_ROWS, $prepare = true, $retry = 0)
    {
        try {
            $time = microtime(true);
            if ($prepare) {
                $this->_statement = $this->pdo()->prepare($sql);
            } else {
                $this->_statement = $this->pdo()->query($sql);
            }
            if (!$this->_statement) {
                $this->error($sql, $params, $prepare ? 'prepare' : 'query');
            }
            if ($prepare) {
                $result = $this->_statement->execute($params);
                if (!$result) {
                    $this->error($sql, $params, 'execute');
                }
            }
            //慢查询日志
            $row_count = $this->_statement->rowCount();
            $cost = (int)((microtime(true) - $time) * 1000);
            if ($cost > $this->getDbSlowTime()) {
                $log = array('cost' => $cost, 'sql' => $sql, 'params' => $params, 'rows' => $row_count);
                DbLogUtil::warning($log, 'db-slow.log');
            }
            $result = $this->fetchResult($mode);
            $this->_pdo->null;
            return ['cost' => $cost, 'result' => $result];
        } catch (\PDOException $e) {
            if ($this->hasLostConnection($e) && $retry < $this->_retryNum) {
                DbLogUtil::info("[asinking/db] Database reconnect...");
                $this->_pdo = null;
                return $this->execute($sql, $params, $mode, $prepare, ++$retry);
            } else {
                $error = $e->getMessage();
                $action = $prepare ? 'prepare' : 'query';
//                DbLogUtil::error("[asinking/db] Database {$action} failed {$error}");
                throw new \Exception("[asinking/db] Database {$action} failed {$error}", 10001);
            }
        }
    }

    /**
     * 获取返回值
     * @param int $mode
     * @return int|array|\PDOStatement
     */
    private function fetchResult($mode)
    {
        $row_count = $this->_statement->rowCount();

        if ($mode == DbResults::AFFECTED_ROWS) {
            return $row_count;
        }
        if ($mode == DbResults::FETCH_ONE) {
            return $row_count ? $this->_statement->fetch(PDO::FETCH_ASSOC) : array();
        }
        if ($mode == DbResults::FETCH_ALL) {
            return $row_count ? $this->_statement->fetchAll(PDO::FETCH_ASSOC) : array();
        }
        if ($mode == DbResults::STATEMENT) {
            return $this->_statement;
        }
        return 0;
    }

    /**
     * 判断是否已断开连接
     * @param $e \Exception
     * @return bool
     */
    protected function hasLostConnection($e)
    {
        if (!$e->getMessage()) {
            $e = $this->getLastError();
        }

        return Str::contains($e->getMessage(), [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'SSL connection has been closed unexpectedly',
        ]);
    }

    /**
     * 错误处理
     * @param string $sql
     * @param array $params
     * @param string $action
     */
    private function error($sql, $params, $action)
    {
        $error = $this->getLastError();
        $errorMessage = '[' . $error->getMessage() . '][' . $sql . ']' . json_encode($params);
        DbLogUtil::info($errorMessage);
        throw new \PDOException("Database {$action} failed {$errorMessage}", 10001);
    }

    /**
     * 获取db配置
     * @return array
     */
    abstract protected function getDbConfig(): array;

    /**
     * 获取慢查询时间
     * @return int
     */
    abstract protected function getDbSlowTime(): int;
}