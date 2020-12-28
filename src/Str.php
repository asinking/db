<?php

namespace asinking\db;

class Str
{

    public static function isEmpty($str)
    {
        if (empty($str) && $str !== 0 && $str !== '0')
            return true;
        else
            return false;
    }

    /**
     * 判断字符串是否包含指定的字串(批量)
     * @param $haystack
     * @param $needles
     * @return bool
     */
    public static function contains($haystack, $needles)
    {
        if (empty($needles)) return false;
        if (!is_array($needles)) {
            $needles = [$needles];
        }
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}