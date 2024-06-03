<?php

require_once realpath(__DIR__ . '/../vendor/autoload.php');

use Bot\TelegramBot;
use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Services\ServiceHandler;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$config = require __DIR__ . '/../config/config.php';

$logger = new Logger($config['logger']['name']);
$logger->pushHandler(new StreamHandler($config['logger']['path'], $config['logger']['level']));

$serviceHandler = new ServiceHandler($logger, $config['selenium'], $config['cache']);
$telegram = new TelegramBot($serviceHandler);
$telegram->setToken($config['telegram']['token']);
echo $telegram->setWebhook($config['telegram']['webhook_url']);

$telegram->processMessage();
?>
