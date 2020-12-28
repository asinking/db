<?php

namespace asinking\db;

class DbLogUtil
{
    const ERROR = 0;
    const WARNING = 1;
    const INFO = 2;
    const DEBUG = 3;

    private static $level = 2;
    private static $path = '';
    private static $format = '%d [%t] [%a] [%u] %m';

    public static function setOption($path, $level = 2)
    {
        self::$path = $path;
        self::$level = $level;
    }

    public static function debug($data, $file = 'debug.log')
    {
        if (self::$level < self::DEBUG) return;

        self::writeLog('DEBUG', $data, $file);
    }

    public static function info($data, $file = 'info.log')
    {
        if (self::$level < self::INFO) return;
        self::writeLog('INFO', $data, $file);
    }

    public static function warning($data, $file = 'warning.log')
    {
        if (self::$level < self::WARNING) return;
        self::writeLog('WARNING', $data, $file);
    }

    public static function error($data, $file = 'error.log')
    {
        if (self::$level < self::ERROR) return;

        self::writeLog('ERROR', $data, $file);
    }

    private static function writeLog($tag, $data, $file)
    {
        if (!self::$path) return;
        $uri = '-';
        $ip = '-';
        $msg = is_scalar($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);

        $find = array('%d', '%t', '%a', '%u', '%m');
        $replace = array(date('Y-m-d H:i:s'), $tag, $ip, $uri, $msg);
        $log = str_replace($find, $replace, self::$format);

        $dir = self::$path . '/' . date('Ymd');
        if ((!is_dir($dir) && !@mkdir($dir, 0777, true)) || !is_writeable($dir)) return;

        $file = $dir . '/' . $file;
        if (file_exists($file) && !is_writeable($file)) {
            try {
                $perms = substr(base_convert(fileperms($file), 10, 8), 3);
                if ($perms == "644") {
                    @chmod($file, 0777);
                }
            } catch (\Exception $e) {
            }
            return;
        }
        file_put_contents($file, $log . "\n", FILE_APPEND);
    }
}