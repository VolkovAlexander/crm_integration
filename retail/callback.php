<?php
/**
 * @author Volkov Alexander
 */

/** Проверка для принятия скрипта системой zadarma */
if (isset($_GET['zd_echo'])) exit($_GET['zd_echo']);

require './../vendor/autoload.php';
require './../lib/RetailToZadarma.php';

$config = include './../config/zadarma.php';

$remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$callerId = filter_input(INPUT_POST, 'caller_id');
$calledDid = filter_input(INPUT_POST, 'called_did');
$callStart = filter_input(INPUT_POST, 'call_start');

define('ZD_IP', '185.45.152.42');

if ($callStart && ($remoteIp == ZD_IP)) {

    $signature = getHeader('Signature');  // Signature is send only if you have your API key and secret
    $signatureTest = base64_encode(hash_hmac('sha1', $callerId . $calledDid . $callStart, $config['secret']));

    if ($signature == $signatureTest) {
        $RetailToZadarma = new \lib\RetailToZadarma();
        $RetailToZadarma->Log->notice('New callback function hire', [
            'callerId' => $callerId,
            'callerDid' => $calledDid,
            'callStart' => $callStart,
            'secret' => $config['secret']
        ]);
        $RetailToZadarma->sendCallEventToCrm($_POST);
    }
}

function getHeader($name)
{
    $headers = getallheaders();
    foreach ($headers as $key => $val) {
        if ($key == $name) return $val;
    }
    return null;
}