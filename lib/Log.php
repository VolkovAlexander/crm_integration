<?php
/**
 * Created by PhpStorm.
 * User: юзер
 * Date: 05.12.2016
 * Time: 10:21
 */

namespace lib;


class Log
{
    public $log_file = __DIR__ . '/./../log.data';

    public function __construct()
    {
        if (!file_exists($this->log_file)) {
            fopen($this->log_file, 'wb');

            if(!file_exists($this->log_file)) {
                die('Can\'t create logs file');
            }
        }
    }

    public function notice($message)
    {
        $this->writeNewMessage(self::NOTICE, $message);
    }

    public function error($message)
    {
        $this->writeNewMessage(self::ERROR, $message);
    }

    public function writeNewMessage($code, $message, $type = 'file')
    {
        switch ($type) {
            case 'file':
                try {
                    $old_data = file_get_contents($this->log_file);

                    file_put_contents($this->log_file,
                        sprintf('<tr class="log-%s"><td>%s</td><td>%s</td><td>%s</td></tr>',
                            $code, date('d.m.Y H:i'), $code, $message
                        ) . PHP_EOL . $old_data
                    );
                } catch (\Exception $e) {
                    die('Can\'t logging events');
                }
                break;
            case 'mysql':
                break;
            default:
                break;
        }
    }

    const NOTICE = 'notice';
    const ERROR = 'error';
}