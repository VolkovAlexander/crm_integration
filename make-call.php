<?php

require 'vendor/autoload.php';
require 'lib/RetailToZadarma.php';

$RetailToZadarma = new \lib\RetailToZadarma();

$clientId = $_GET['clientId'];
$code = $_GET['code'];
$phone = $_GET['phone'];

$result = $RetailToZadarma->makeCallbackToPhone($code, $phone);