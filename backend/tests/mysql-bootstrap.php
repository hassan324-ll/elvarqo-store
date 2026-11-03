<?php

// This suite migrates/wipes its destination. Never point it at a business database.
$database = getenv('MYSQL_TEST_DATABASE') ?: '';
$username = getenv('MYSQL_TEST_USERNAME');
if (! preg_match('/^elvarqo_(?:test|compat)_[a-z0-9_]+$/D', $database) || $username === false) {
    throw new RuntimeException('Set MYSQL_TEST_DATABASE to a disposable elvarqo_test_* schema and MYSQL_TEST_USERNAME explicitly.');
}
$values = [
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
    'DB_HOST' => getenv('MYSQL_TEST_HOST') ?: '127.0.0.1', 'DB_PORT' => getenv('MYSQL_TEST_PORT') ?: '3306',
    'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => getenv('MYSQL_TEST_PASSWORD') ?: '',
    'WISE_ENABLED' => 'false',
];
foreach ($values as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../vendor/autoload.php';
