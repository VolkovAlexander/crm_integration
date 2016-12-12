<?php
require './../vendor/autoload.php';
require './../lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();
$data = $RetailToZadarma->Mysql->table('retail')->select('*')->get();

print_r($data);

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
            <?php foreach($data as $row): ?>
                <tr>
                    <td><?= date('d-M-y H:i', $row['created_at']) ?></td>
                    <td><?= $row['status'] ?></td>
                    <td><pre><?= print_r(json_encode($row['data'], true), true) ?></pre></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
