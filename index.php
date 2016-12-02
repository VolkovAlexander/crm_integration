<?php

require 'vendor/autoload.php';
require 'lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();

//$RetailToZadarma->registrateSipInCrm();
//$RetailToZadarma->sendCallRequestToCrm('+79193188295', [101], 'in');

$result = $RetailToZadarma->makeCallbackToPhone('100', '101');

print_r($result);