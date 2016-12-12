<?php
require './../vendor/autoload.php';

$RetailToZadarma = new \lib\RetailToZadarma();
$data = $RetailToZadarma->Mysql->table('retail')->select(['*']);

print_r($data);

?>
<meta http-equiv="refresh" content="3">
<style>
    table, tr, td { border: 1px solid black; border-collapse: collapse; }
    table { width: 100%; }
    td { padding: 1px 10px; }
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
    </tbody>
</table>
