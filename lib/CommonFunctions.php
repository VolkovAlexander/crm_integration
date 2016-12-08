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

    public static function saveZdConfigToFile($key, $secret)
    {

        $new_config_data = sprintf('<?php return [ \'key\' => \'%s\', \'secret\' => \'%s\'];', addslashes($key), addslashes($secret));
        @file_put_contents('./../config/zadarma.php', $new_config_data);
    }

    public static function saveRetailConfigToFile($name, $key)
    {
        $new_config_data = sprintf('<?php return [ \'username\' => \'%s\', \'url\' => \'https://%s.retailcrm.ru/\', \'key\' => \'%s\' ];',
            addslashes($name), addslashes($name), addslashes($key)
        );
        @file_put_contents('./../config/retail.php', $new_config_data);
    }
}