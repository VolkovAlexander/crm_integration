<?php

require './../vendor/autoload.php';
require './../lib/RetailToZadarma.php';

if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

$RetailToZadarma = new \lib\RetailToZadarma();

$RetailToZadarma->Log->notice($ip);

list($code, $phone) = $RetailToZadarma->validateCallbackParams($_GET);
$RetailToZadarma->makeCallbackToPhone($code, $phone);