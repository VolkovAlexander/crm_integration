<?php
/**
 * @author Volkov Alexander
 */

$config = include 'config/zadarma.php';

if (isset($_GET['zd_echo'])) exit($_GET['zd_echo']);

$remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$callerId = filter_input(INPUT_POST, 'caller_id'); // number of calling party;
$calledDid = filter_input(INPUT_POST, 'called_did'); // number of called party;
$callStart = filter_input(INPUT_POST, 'call_start'); // start time of call

if ($callStart && ($remoteIp == $config['ip'])) {

    $signature = getHeader('Signature');  // Signature is send only if you have your API key and secret
    $signatureTest = base64_encode(hash_hmac('sha1', $callerId . $calledDid . $callStart, $config['secret']));

    if ($signature == $signatureTest) {
        $params = $_GET;
        // Do your actions
        /*
        // For each request you can easily change work scenario for current call by sending in response on information:
        echo json_encode(array(
            'redirect' => 1,
            'caller_name' => 'TestName'
        ));
        exit();
        */
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