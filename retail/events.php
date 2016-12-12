<?php
require './../vendor/autoload.php';
require './../lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();
$data = $RetailToZadarma->Mysql->table('retail')->select('*')->orderBy('id', 'DESC')->get();

?>
<meta http-equiv="refresh" content="5">
<style>
    table, tr, td {
        border: 1px solid black;
        border-collapse: collapse;
    }

    table {
        width: 100%;
    }

    tr.status-0 {
        background-color: lightblue;
    }

    tr.status-1 {
        background-color: aqua
    }

    tr.status-2 {
        background-color: lightgreen;
    }

    tr.status-3 {
        background-color: lightgray;
    }

    td {
        padding: 1px 10px;
    }
</style>

<table>
    <tbody>
    <?php if (!empty($data)): ?>
        <?php foreach ($data as $Row): ?>
            <tr class="status-<?= $Row->status ?>">
                <td colspan="3"><?= $Row->call_id ?></td>
            </tr>
            <tr>
                <td><?= date('H:i', $Row->created_at) ?></td>
                <td>
                    <pre><b>Start data: </b><br><?= print_r(json_decode($Row->start_data, true), true) ?></pre>
                    <pre><b>Finish data: </b><br><?= print_r(json_decode($Row->end_data, true), true) ?></pre>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
