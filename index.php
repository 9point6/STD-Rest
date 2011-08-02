<?php

require_once("lib/class.apifactory.php");

$api = new APIFactory();
$api->fromJSON("./apis/lastfm.json");

$api->set_param("api_key","a7415856c959bfbb1c1369cb50ea7212");
$api->api_key = "a7415856c959bfbb1c1369cb50ea7212";

print_r($api->album->getBuyLinks->mbid(array("mbid" => "12b93d10-c4fd-3058-830b-ae2c19145ab7"))); die;
$api->album->getInfo();

print_r($api);