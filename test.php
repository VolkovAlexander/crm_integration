<?php
/**
 * Created by PhpStorm.
 * User: юзер
 * Date: 02.12.2016
 * Time: 18:23
 */

require 'vendor/autoload.php';
require 'lib/RetailToZadarma.php';

$Client = new \lib\RetailToZadarma();
$result = $Client->getCallRecord(null, 'in_dfa5a7a6b6d035160cbafd6eae252b71a7a812c8');

print_r($result);