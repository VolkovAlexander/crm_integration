<?php

define('ROOT_DIR', './../');

require ROOT_DIR . 'vendor/autoload.php';
require ROOT_DIR . 'lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();

list($code, $phone) = $RetailToZadarma->validateCallbackParams($_GET);
$RetailToZadarma->makeCallbackToPhone($code, $phone);