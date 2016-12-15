<?php
/**
 * @author VolkovAlexander
 */

require './../vendor/autoload.php';
require './../lib/MegaplanToZadarma.php';

$host = filter_input(INPUT_POST, 'host');
$login = filter_input(INPUT_POST, 'login');
$password = filter_input(INPUT_POST, 'password');

if (!empty($host) && !empty($login) && !empty($password)) {
    \lib\CommonFunctions::saveMegaplanConfigToFile($host, $login, $password);
}

$zd_key = filter_input(INPUT_POST, 'zd_key');
$zd_secret = filter_input(INPUT_POST, 'zd_secret');

if (!empty($zd_key) && !empty($zd_secret)) {
    \lib\CommonFunctions::saveZdConfigToFile($zd_key, $zd_secret);
}

$crm_config = include './../config/megaplan.php';
$zd_config = include './../config/zadarma.php';

$testClient = new \lib\MegaplanToZadarma();
$is_connection_success = false;

try {
    $is_connection_success = $testClient->validateClients();
} catch (Exception $e) {
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Zadarma <-> Megaplan</title>
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
                <b>Megaplan</b>
            </h3>
            <hr>
        </div>
        <div class="col-md-3">
            <form method="POST" action="index.php">
                <h3>Megaplan</h3>
                <div class="form-group">
                    <label for="exampleInputEmail1">Url Вашей CRM</label>
                    <div class="input-group">
                        <div class="input-group-addon">https://</div>
                        <input name="host" type="text" class="form-control" placeholder="url"
                               value="<?= \lib\CommonFunctions::nullableFromArray($crm_config, 'host') ?>">
                    </div>

                </div>
                <div class="form-group">
                    <label for="exampleInputPassword1">Логин для доступа к API Вашей CRM</label>
                    <input name="login" type="text" class="form-control" placeholder="Логин"
                           value="<?= lib\CommonFunctions::nullableFromArray($crm_config, 'login') ?>">
                </div>
                <div class="form-group">
                    <label for="exampleInputPassword1">Пароль для доступа к API Вашей CRM</label>
                    <input name="password" type="password" class="form-control" placeholder="Пароль"
                           value="<?= lib\CommonFunctions::nullableFromArray($crm_config, 'password') ?>">
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
                        if (empty($testClient->cCrm) && empty($testClient->cZadarma)) {
                            echo 'для всех клиентов';
                        } elseif (empty($testClient->cCrm)) {
                            echo 'для клиента RetailCRM';
                        } elseif (empty($testClient->cZadarma)) {
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
            <?php if (isset($_GET['logs'])): ?>
                <iframe src="logs.php" style="width: 100%; height: 380px" frameborder="no">
                </iframe>
            <?php endif; ?>
            <pre>
                <?php
                    $response = $testClient->cCrm->get('/BumsCrmApiV01/Contractor/list.api', [

                    ]);

                    print_r($response);
                ?>
            </pre>
        </div>
    </div>
</div>
</body>
</html>
