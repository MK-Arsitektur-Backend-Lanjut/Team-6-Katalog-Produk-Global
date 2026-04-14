<?php
require __DIR__ . '/vendor/autoload.php';

$openapi = \OpenApi\Generator::scan([\realpath(__DIR__ . '/app')]);
header('Content-Type: application/json');
echo $openapi->toJson();
