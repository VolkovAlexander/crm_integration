<?php
/**
 * @author VolkovAlexander
 */

define('ROOT_DIR', './../');

require ROOT_DIR . 'vendor/autoload.php';
require ROOT_DIR . 'lib/RetailToZadarma.php';

$name = addslashes(filter_input(INPUT_POST, 'name'));
$key = addslashes(filter_input(INPUT_POST, 'key'));

if (!empty($name) && !empty($key)) {
    $new_config_data = sprintf('
<?php
    return [
        \'username\' => \'%s\',
        \'url\' => \'https://%s.retailcrm.ru/\',
        \'key\' => \'%s\'
    ];
    ', $name, $name, $key);

    @file_put_contents(ROOT_DIR . 'config/retail.php', $new_config_data);
}

$config = include ROOT_DIR . 'config/retail.php';

$testClient = new \lib\RetailToZadarma();
$is_connection_success = false;

try {
    $testClient->registrateSipInCrm();
    $is_connection_success = $testClient->validateClients();
} catch (Exception $e) {}

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
            <h5>Не забудьте настроить внутри CRM связки "менеджеры <-> добавочные коды" и "сайты <-> внешние номера"</h5>
            <hr>
        </div>
        <div class="col-md-3">
            <form method="POST" action="index.php">
                <div class="form-group">
                    <label for="exampleInputEmail1">Наименование Вашей CRM</label>
                    <div class="input-group">
                        <input name="name" type="text" class="form-control" placeholder="username"
                               value="<?= $config['username'] ?>">
                        <div class="input-group-addon">.retailcrm.ru</div>
                    </div>

                </div>
                <div class="form-group">
                    <label for="exampleInputPassword1">Ключ для доступа к API Вашей CRM</label>
                    <input name="key" type="password" class="form-control" placeholder="API key"
                           value="<?= $config['key'] ?>">
                </div>
                <?php if (!$is_connection_success): ?>
                    <b style="color: red;">Неверные настройки подключения</b>
                    <br><br>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" style="width: 100%">Обновить данные для подключения</button>
            </form>
        </div>
        <div class="col-md-9">
            <iframe src="logs.php" style="width: 100%; height: 380px" frameborder="no">
            </iframe>
        </div>
    </div>
</div>
</body>
</html>
