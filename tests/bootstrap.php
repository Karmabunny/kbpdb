<?php
define('ROOT_DIR', dirname(__DIR__));

require ROOT_DIR . '/vendor/autoload.php';

\Dotenv\Dotenv::createUnsafeImmutable(ROOT_DIR)->safeLoad();
