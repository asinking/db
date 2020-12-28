<?php

namespace asinking\db;

class DbResults
{
    const AFFECTED_ROWS = 1; //影响行数
    const FETCH_ONE = 2; //单行记录
    const FETCH_ALL = 3; //多行记录
    const STATEMENT = 4; //原始结果集
}