<?php

require 'vendor/autoload.php';
require 'lib/RetailToZadarma.php';

$url = isset($_POST['url']) ? $_POST['url'] : null;
$api_code = isset($_POST['api_code']) ? $_POST['api_code'] : null;

$zd_key = isset($_POST['zd_key']) ? $_POST['zd_key'] : null;
$zd_secret = isset($_POST['zd_secret']) ? $_POST['zd_secret'] : null;

$managers_to_codes = isset($_POST['manager_code']) ? $_POST['manager_code'] : null;
$shops_to_phones = isset($_POST['shop_phone']) ? $_POST['shop_phone'] : null;

$internal_codes = [];
$managers = [];
$external_phones = [];
$shops = [];

$errors = [];

if (!empty($url) && !empty($api_code) && !empty($zd_key) && !empty($zd_secret)) {
    $RetailToZadarma = null;

    try {
        $RetailToZadarma = new \lib\RetailToZadarma([
            'key' => $zd_key,
            'secret' => $zd_secret
        ], [
            'url' => $url,
            'key' => $api_code
        ]);
    } catch (\Exception $e) {
        $errors[] = 'Неверные входные данные';
    }


    if (!empty($managers_to_codes) && !empty($shops_to_phones)) {
        $additionalCodes = [];
        $externalPhones = [];

        foreach ($managers_to_codes as $manager_id => $internal_code) {
            $additionalCodes[] = [
                'userId' => $manager_id, 'code' => $internal_code
            ];
        }



        foreach ($shops_to_phones as $shop_name => $external_phone) {
            $externalPhones[] = [
                'siteCode' => $shop_name, 'externalPhone' => $external_phone
            ];
        }

        $save_result = $RetailToZadarma->registrateSipInCrm(
            $additionalCodes, $externalPhones
        );

        if($save_result) {
            die('Телефония Zadarma успешно была интегрирована в Ваш аккаунт RetailCRM');
        }
    }

    if (empty($errors)) {
        try {
            $response = $RetailToZadarma->cZadarma->call('/v1/pbx/internal/', [], 'GET');
            $RetailToZadarma->validateZdResponse($response);
            $response = json_decode($response, true);
            $internal_codes = isset($response['numbers']) ? array_values($response['numbers']) : [];

            $response = $RetailToZadarma->cCrm->usersList([
                'isManager' => true
            ]);
            $RetailToZadarma->validateCrmResponse($response);
            $managers = $response['users'];

            $response = $RetailToZadarma->cZadarma->call('/v1/direct_numbers/', [], 'GET');
            $RetailToZadarma->validateZdResponse($response);
            $response = json_decode($response, true);
            $external_phones = isset($response['info']) ? array_values($response['info']) : [];

            $response = $RetailToZadarma->cCrm->sitesList();
            $RetailToZadarma->validateCrmResponse($response);
            $shops = $response['sites'];
        } catch (Exception $e) {
            $RetailToZadarma->Log->error(sprintf('Can\'t load user data (%s)', $e->getMessage()));
        }
    }

}

?>


<style>
    input {
        margin-bottom: 10px;
        display: block;
        width: 100%
    }

    table {
        width: 100%;
    }

    td {
        padding: 5px 10px 0 0;
    }

    table tr td select {
        width: 100%;
        height: 35px;
    }
</style>


<h3>Интеграция с системой RetailCRM</h3>

<form method="post" action="/install.php">
    <div style="width: 300px; float: left">
        <label>
            <b>URL для доступа к CRM</b>
            <input name="url" type="text" placeholder="http://<username>.retailcrm.ru" value="<?= $url ?>">
        </label>
        <label>
            <b>Ключ API вашей CRM</b>
            <input name="api_code" type="text" placeholder="API key" value="<?= $api_code ?>">
        </label>
        <br>
        <label>
            <b>Ключ доступа к zadarma</b>
            <input name="zd_key" type="text" placeholder="key" value="<?= $zd_key ?>">
        </label>
        <label>
            <b>Секрет для доступа к zadarma</b>
            <input name="zd_secret" type="text" placeholder="secret" value="<?= $zd_secret ?>">
        </label>
        <ul style="color: darkred; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
        </ul>
        <br>
        <input type="submit" value="Передать данные">
    </div>
    <div style="width: 800px; float: left; margin-left: 50px;">
        <?php if (empty($internal_codes) || empty($managers)): ?>
            <p style="border: 2px dashed grey; padding: 10px 40px;">Данные станут доступны после отправки основных
                параметров</p>
        <?php else: ?>
            <div style="width: 50%; float: left;">
                <b>Связка менеджеров и добавочных номеров</b>
                <table>
                    <?php foreach ($managers as $manager): ?>
                        <tr>
                            <td>
                                <select name="manager_code[<?= $manager['id'] ?>]"
                                        value="manager_code[<?= $manager['id'] ?>]">
                                    <?php foreach ($internal_codes as $internal_code): ?>
                                        <option value="<?= $internal_code ?>"><?= $internal_code ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?= $manager['firstName'] ?> <?= $manager['lastName'] ?>
                                <br><i><?= $manager['email'] ?></i>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div style="width: 50%; float: left;">
                <b>Связка магазинов и прямых номеров</b>
                <table>
                    <?php foreach ($shops as $shop): ?>
                        <tr>
                            <td>
                                <select name="shop_phone[<?= $shop['name'] ?>]">
                                    <?php foreach ($external_phones as $external_phone): ?>
                                        <option value="<?= $external_phone['number'] ?>"><?= $external_phone['number'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?= $shop['name'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</form>

