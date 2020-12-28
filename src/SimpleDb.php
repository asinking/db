<?php

namespace asinking\db;


/**
 * 简易Db操作类
 * Class Db
 * @package asinking\db
 */
class SimpleDb extends DbDriver
{
    /**
     * 慢查询时间
     * @var null
     */
    public $slowTime = 200;
    /**
     * db配置
     *  array(
    'dsn' => "mysql:host=127.0.0.1;port=3306;dbname=ak_dc;charset=utf8",
    'username' => $username,
    'passwd' => $passwd,
    'options' => [PDO::ATTR_TIMEOUT => 1]
    )
     * @var array
     */
    public $config = array();

    /**
     * 获取db配置
     * @return array
     */
    protected function getDbConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array $config
     * @return $this|mixed
     */
    protected function setDbConfig(array $config)
    {
         $this->config=$config;
         return $this;
    }

    /**
     * 慢查询时间
     * @return int
     */
    protected function getDbSlowTime(): int
    {
        return $this->slowTime;
    }

    /**
     * @return SimpleDb
     */
    public static function query()
    {
        return new static();
    }

}