# db
简易Db查询
## 查询操作
```javascript

DbResults::AFFECTED_ROWS  #查询结果条数
DbResults::FETCH_ALL      #查询结果集

SimpleDb::query()->setDbConfig([
            'host' => '127.0.0.2107',
            'port'=> '3306',
            'dbName'=> "dbname",
            'username' => 'test',
            'pwd' => 'test123',
        ])->execute("SELECT * FROM dc_app",[],DbResults::AFFECTED_ROWS)
```
