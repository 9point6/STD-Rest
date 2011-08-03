<?php

require_once("lib/class.apifactory.php");

$api = new APIFactory();
$api->fromJSON("./apis/mobicart.json");

$api->api_key = "a7415856c959bfbb1c1369cb50ea7212";
$api->user_name = "richthegeek@gmail.com";

$api->store->get_settings(42);