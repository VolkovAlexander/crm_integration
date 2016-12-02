<?php

require 'vendor/autoload.php';
require 'lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();

$clientId = (string)$_GET['clientId'];
$code = (string)$_GET['code'];
$phone = sprintf('+%s', str_replace(' ', '', $_GET['phone']));

$result = $RetailToZadarma->makeCallbackToPhone($code, $phone);

echo '<pre>';
print_r($result);
echo '</pre>';