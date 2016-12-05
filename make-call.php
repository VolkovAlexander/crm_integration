<?php

require 'vendor/autoload.php';
require 'lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();

$clientId = (string)$_GET['clientId'];
$code = (string)$_GET['code'];
$phone = sprintf('+%s', str_replace(' ', '', $_GET['phone']));

$RetailToZadarma->makeCallbackToPhone($code, $phone);