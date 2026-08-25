<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$testDatabaseUrl = trim((string) (
    $_SERVER['TEST_DATABASE_URL']
    ?? $_ENV['TEST_DATABASE_URL']
    ?? getenv('TEST_DATABASE_URL')
    ?: $_SERVER['DATABASE_URL']
    ?? $_ENV['DATABASE_URL']
    ?? getenv('DATABASE_URL')
    ?: ''
));

if ($testDatabaseUrl === '') {
    throw new RuntimeException(
        'A test database URL is required for PHPUnit. Refusing to boot without an isolated database.',
    );
}

$databasePath = parse_url($testDatabaseUrl, PHP_URL_PATH);
$databaseName = is_string($databasePath) ? rawurldecode(ltrim($databasePath, '/')) : '';

if ($databaseName === '' || !str_ends_with($databaseName, '_test')) {
    throw new RuntimeException(sprintf(
        'Unsafe PHPUnit database "%s". Tests may only run against a database whose name ends in "_test".',
        $databaseName !== '' ? $databaseName : '(unknown)',
    ));
}

putenv('DATABASE_URL='.$testDatabaseUrl);
$_ENV['DATABASE_URL'] = $testDatabaseUrl;
$_SERVER['DATABASE_URL'] = $testDatabaseUrl;
