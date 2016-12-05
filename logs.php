<?php
/**
 * Created by PhpStorm.
 * User: юзер
 * Date: 05.12.2016
 * Time: 10:35
 */

$data = file_exists(__DIR__ . '/log.data') ? file_get_contents(__DIR__ . '/log.data') : null;

?>

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
        </tr>
    </thead>
    <tbody>
        <?= $data ?>
    </tbody>
</table>
