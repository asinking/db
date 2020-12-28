<?php

namespace asinking\db;

use PDO;

abstract class DbDriver
{
    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var \PDOStatement
     */
    private $statement;

    public function __construct()
    {

    }

    /**
     * @return PDO
     */
    private function pdo($retry = 0)
    {
        if ($this->pdo) {
            return $this->pdo;
        }
        try {
            $config = $this->getDbConfig();
            $this->pdo = new PDO($config['dsn'], $config['username'], $config['passwd'], $config['options']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
            $this->pdo->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
            $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\PDOException $e) {
            if ($retry < 3) {
                $this->pdo = null;
                return $this->pdo(++$retry);
            }
            throw $e;
        }
        return $this->pdo;
    }

    /**
     * @return DbDriver
     * @throws \Exception
     */
    public static function query()
    {
        return new static();
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
        if (!$this->pdo) {
            return new \Error('', 0);
        }

        $errorInfo = null;
        if ($this->statement) {
            $errorInfo = $this->statement->errorInfo();
        }
        if (!$errorInfo || !$errorInfo[2]) {
            $errorInfo = $this->pdo->errorInfo();
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
                $this->statement = $this->pdo()->prepare($sql);
            } else {
                $this->statement = $this->pdo()->query($sql);
            }
            if (!$this->statement) {
                $this->error($sql, $params, $prepare ? 'prepare' : 'query');
            }
            if ($prepare) {
                $result = $this->statement->execute($params);
                if (!$result) {
                    $this->error($sql, $params, 'execute');
                }
            }
            //慢查询日志
            $row_count = $this->statement->rowCount();
            $cost = (int)((microtime(true) - $time) * 1000);
            if ($cost > $this->getDbSlowTime()) {
                $log = array('cost' => $cost, 'sql' => $sql, 'params' => $params, 'rows' => $row_count);
                DbLogUtil::warning($log, 'db-slow.log');
            }
            $result = $this->fetchResult($mode);
            $this->pdo->null;
            return ['cost' => $cost, 'result' => $result];
        } catch (\PDOException $e) {
            if ($this->hasLostConnection($e) && $retry < 3) {
                DbLogUtil::warning("[asinking/db] Database reconnect...", 'asinking-db.log');
                $this->pdo = null;
                return $this->execute($sql, $params, $mode, $prepare, ++$retry);
            } else {
                $error = $this->getLastError();
                $errorMessage = '[' . $error->getMessage() . '][' . $sql . ']' . json_encode($params);
                $action = $prepare ? 'prepare' : 'query';
                DbLogUtil::error("[asinking/db] Database {$action} failed {$errorMessage}");
                throw new \Exception("Database {$action} failed {$errorMessage}", 10001);
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
        $row_count = $this->statement->rowCount();

        if ($mode == DbResults::AFFECTED_ROWS) {
            return $row_count;
        }
        if ($mode == DbResults::FETCH_ONE) {
            return $row_count ? $this->statement->fetch(PDO::FETCH_ASSOC) : array();
        }
        if ($mode == DbResults::FETCH_ALL) {
            return $row_count ? $this->statement->fetchAll(PDO::FETCH_ASSOC) : array();
        }
        if ($mode == DbResults::STATEMENT) {
            return $this->statement;
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
        throw new \PDOException("Database {$action} failed", 10001);
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