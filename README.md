# db
简易Db查询
## 查询操作
```javascript
SimpleDb::query()->setDbConfig([
            'host' => '127.0.0.2107',
            'port'=> '3306',
            'database'=> "dbname",
            'username' => 'test',
            'passwd' => 'test123',
        ])->execute("SELECT * FROM dc_app",[],DbResults::AFFECTED_ROWS)
```
