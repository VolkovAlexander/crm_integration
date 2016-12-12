<?php
require './../vendor/autoload.php';
require './../lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();
$data = $RetailToZadarma->Mysql->table('retail')->select('*')->get();

?>
<meta http-equiv="refresh" content="3">
<style>
    table, tr, td {
        border: 1px solid black;
        border-collapse: collapse;
    }

    table {
        width: 100%;
    }

    td {
        padding: 1px 10px;
    }
</style>

<table>
    <thead>
    <tr>
        <td class="col-date">Date</td>
        <td>Status</td>
        <td>Additional</td>
    </tr>
    </thead>
    <tbody>
        <?php if(!empty($data)): ?>
            <?php foreach($data as $Row): ?>
                <tr>
                    <td><?= date('d-M-y H:i', $Row->created_at) ?></td>
                    <td><?= $Row->status ?></td>
                    <td><pre><?php print_r(json_decode($Row->data)) ?></pre></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
