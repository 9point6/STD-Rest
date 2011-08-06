<?php

require_once("php/class.apifactory.php");

$s = microtime(true);

$api = new APIFactory();
$api->fromJSON("./apis/mobicart.json");