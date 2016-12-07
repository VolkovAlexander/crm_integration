<?php

require 'vendor/autoload.php';
require 'lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();

list($code, $phone) = $RetailToZadarma->validateCallbackParams($_GET);
$RetailToZadarma->makeCallbackToPhone($code, $phone);