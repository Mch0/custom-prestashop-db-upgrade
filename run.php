<?php

ini_set('display_errors', 1);
require_once __DIR__ . "/CustomUpgrader.php";

$upgrader = new CustomUpgrader('1.7.4.0', "1.7.8.11");
$upgrader->run();