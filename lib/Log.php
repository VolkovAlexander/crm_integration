<?php
/**
 * @author Volkov Alexander
 */

namespace lib;

/**
 * Class Log
 * @package lib
 */
class Log
{
    public static $log_file = 'log.data';
    public $mode = 'file';

    /**
     * @inheritdoc
     */
    public function __construct()
    {
        self::$log_file = __DIR__ . '/./../' . self::$log_file;

        if (!file_exists(self::$log_file)) {
            fopen(self::$log_file, 'wb');

            if (!file_exists(self::$log_file)) {
                die('Can\'t create logs file');
            }
        }
    }

    /**
     * Запись уведомления в логи
     * @param $message
     */
    public function notice($message)
    {
        $this->writeNewMessage(self::NOTICE, $message);
    }

    /**
     * Запись ошибки в логи
     * @param $message
     */
    public function error($message)
    {
        $this->writeNewMessage(self::ERROR, $message);
    }

    /**
     * Внутренняя функция записи данных в лог
     * @param $code
     * @param $message
     */
    private function writeNewMessage($code, $message)
    {
        switch ($this->mode) {
            case 'file':
                try {
                    $old_data = file_get_contents(self::$log_file);

                    file_put_contents(self::$log_file,
                        sprintf('<tr class="log-%s"><td>%s</td><td>%s</td><td>%s</td></tr>',
                            $code, date('d.m.Y H:i'), $code, $message
                        ) . PHP_EOL . $old_data
                    );
                } catch (\Exception $e) {
                    die('Can\'t logging events');
                }
                break;
            default:
                break;
        }
    }

    const NOTICE = 'notice';
    const ERROR = 'error';
}