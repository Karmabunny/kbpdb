<?php

// return [
//     'type' => 'sqlite',
//     'dsn' => __DIR__ . '/db.sqlite',
// ];

return [
    'type' => 'mysql',
    'host' => getenv('SITES_DB_HOSTNAME') ?: '127.0.0.1',
    'user' => getenv('SITES_DB_USERNAME') ?: 'kbpdb',
    'pass' => getenv('SITES_DB_PASSWORD') ?: 'password',
    'database' => getenv('SITES_DB_DATABASE') ?: 'kbpdb',
];
