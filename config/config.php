<?php

return [
    'telegram' => [
        'token' => $_ENV['TELEGRAM_BOT_TOKEN'],
        'webhook_url' => $_ENV['WEBHOOK_URL'],
    ],
    'selenium' => [
        'host' => 'http://localhost:4444/',
        'chrome_options' => [
            '--no-sandbox',
            '--headless',
        ],
    ],
    'cache' => [
        'services_cache' => __DIR__ . '/../src/services_cache.json',
        'cache_time' => 3600, // 1 hour
    ],
    'logger' => [
        'name' => 'telegram_bot',
        'path' => __DIR__ . '/../logs/app.log',
        'level' => \Monolog\Logger::DEBUG,
    ],
];
