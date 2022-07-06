<?php

require __DIR__.'/vendor/autoload.php';

use Monolog\Logger;

$logger = new Logger('channel-name');
$logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__.'/app.log', Logger::DEBUG));

$logger->info('This is a log! ^_^ ');