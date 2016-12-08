<?php

if (!empty($_POST)) {
    require './../vendor/autoload.php';
    require './../lib/Log.php';

    $Log = new \lib\Log('megaplan');
    $Log->notice('new event from crm', $_POST);
}


