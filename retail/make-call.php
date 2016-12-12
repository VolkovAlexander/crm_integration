<?php

require './../vendor/autoload.php';
require './../lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();

list($code, $phone) = $RetailToZadarma->validateCallbackParams($_GET);
error_log($code . ' ' . $phone);

$RetailToZadarma->makeCallbackToPhone($code, $phone);