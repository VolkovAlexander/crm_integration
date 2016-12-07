<?php
/**
 * @author Volkov Alexander
 */

namespace lib;


class CommonFunctions
{
    public static function nullableFromArray($array, $key)
    {
        return isset($array[$key]) ? $array[$key] : null;
    }
}