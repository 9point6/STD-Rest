<?php

require_once("php/class.apifactory.php");

$api = new APIFactory();
$api->fromJSON("./apis/mobicart.json");

$api->api_key = "lololol";
$api->user_name = "richthegeek@gmail.com";
$api->store->get_tax(4525);