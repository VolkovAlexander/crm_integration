<?php
/**
 * Created by PhpStorm.
 * User: юзер
 * Date: 05.12.2016
 * Time: 10:35
 */

define('ROOT_DIR', './../');

require ROOT_DIR . 'lib/Log.php';

$log_filename = sprintf('%s/%s', __DIR__, \lib\Log::$log_file);
$data = file_exists($log_filename) ? file_get_contents($log_filename) : null;

?>
<meta http-equiv="refresh" content="3">
<style>
    table, tr, td { border: 1px solid black; border-collapse: collapse; }
    table { width: 100%; }
    td { padding: 1px 10px; }

    tr.log-notice td { background-color: lightblue }
    tr.log-error td { background-color: lightcoral }
</style>

<table>
    <thead>
        <tr>
            <td class="col-date">Date</td>
            <td>Code</td>
            <td>Message</td>
            <td>Additional</td>
        </tr>
    </thead>
    <tbody>
        <?= $data ?>
    </tbody>
</table>
