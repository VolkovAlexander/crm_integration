<?php
/**
 * @author VolkovAlexander
 */

require './../vendor/autoload.php';
require './../lib/RetailToZadarma.php';

$name = filter_input(INPUT_POST, 'name');
$key = filter_input(INPUT_POST, 'key');

if (!empty($name) && !empty($key)) {
    \lib\CommonFunctions::saveRetailConfigToFile($name, $key);
}

$zd_key = filter_input(INPUT_POST, 'zd_key');
$zd_secret = filter_input(INPUT_POST, 'zd_secret');

if (!empty($zd_key) && !empty($zd_secret)) {
    \lib\CommonFunctions::saveZdConfigToFile($zd_key, $zd_secret);
}

$crm_config = include './../config/retail.php';
$zd_config = include './../config/zadarma.php';

$testClient = new \lib\RetailToZadarma();
$is_connection_success = false;

try {
    $testClient->registrateSipInCrm();
    $is_connection_success = $testClient->validateClients();
} catch (Exception $e) {
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Zadarma <-> RetailCRM</title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
          integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-md-12 text-center">
            <h3>
                Интеграция телефонии <b>Zadarma</b> в
                <div style="background-color: <?= $is_connection_success ? 'lightgreen' : 'red' ?>; margin-left: 10px; border-radius: 20px; width: 20px; height: 20px; display: inline-block"></div>
                <b>RetailCRM</b>
            </h3>
            <h5>Не забудьте настроить внутри CRM связки "менеджеры <-> добавочные коды" и "сайты <-> внешние
                номера"</h5>
            <hr>
        </div>
        <div class="col-md-3">
            <form method="POST" action="index.php">
                <h3>RetailCRM</h3>
                <div class="form-group">
                    <label for="exampleInputEmail1">Наименование Вашей CRM</label>
                    <div class="input-group">
                        <input name="name" type="text" class="form-control" placeholder="username"
                               value="<?= \lib\CommonFunctions::nullableFromArray($crm_config, 'username') ?>">
                        <div class="input-group-addon">.retailcrm.ru</div>
                    </div>

                </div>
                <div class="form-group">
                    <label for="exampleInputPassword1">Ключ для доступа к API Вашей CRM</label>
                    <input name="key" type="password" class="form-control" placeholder="API key"
                           value="<?= lib\CommonFunctions::nullableFromArray($crm_config, 'key') ?>">
                </div>
                <hr>
                <h3>Zadarma</h3>
                <div class="form-group">
                    <label for="exampleInputEmail1">Key</label>
                    <input name="zd_key" type="text" class="form-control" placeholder="key"
                           value="<?= \lib\CommonFunctions::nullableFromArray($zd_config, 'key') ?>">

                </div>
                <div class="form-group">
                    <label for="exampleInputPassword1">Secret</label>
                    <input name="zd_secret" type="password" class="form-control" placeholder="secret"
                           value="<?= \lib\CommonFunctions::nullableFromArray($zd_config, 'secret') ?>">
                </div>
                <hr>
                <?php if (!$is_connection_success): ?>
                    <b style="color: red;">
                        Неверные настройки
                        <?php
                            if(empty($testClient->cCrm) && empty($testClient->cZadarma)) {
                                echo 'для всех клиентов';
                            } elseif(empty($testClient->cCrm)) {
                                echo 'для клиента RetailCRM';
                            } elseif(empty($testClient->cZadarma)) {
                                echo 'для клиента Zadarma';
                            }
                        ?>
                    </b>
                    <br><br>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" style="width: 100%">Обновить данные для подключения
                </button>
            </form>
        </div>
        <div class="col-md-9">
            <?php if(isset($_GET['logs'])): ?>
            <iframe src="logs.php" style="width: 100%; height: 380px" frameborder="no">
            </iframe>
            <?php endif; ?>
            <iframe src="events.php" style="width: 100%; height: 380px" frameborder="no">
            </iframe>
        </div>
    </div>
</div>
</body>
</html>
