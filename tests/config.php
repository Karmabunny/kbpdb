<?php

// return [
//     'type' => 'sqlite',
//     'dsn' => __DIR__ . '/db.sqlite',
// ];

return [
    'type' => 'mysql',
    'host' => getenv('SITES_DB_HOSTNAME') ?: '127.0.0.1',
    'user' => 'kbpdb',
    'pass' => 'password',
    'database' => 'kbpdb',
];
