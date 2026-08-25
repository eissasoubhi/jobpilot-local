<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$testDatabaseUrl = trim((string) (
    $_SERVER['TEST_DATABASE_URL']
    ?? $_ENV['TEST_DATABASE_URL']
    ?? getenv('TEST_DATABASE_URL')
    ?: ''
));

if ($testDatabaseUrl === '') {
    throw new RuntimeException(
        'TEST_DATABASE_URL is required for PHPUnit. Refusing to run tests against the application database.',
    );
}

$databasePath = parse_url($testDatabaseUrl, PHP_URL_PATH);
$databaseName = is_string($databasePath) ? rawurldecode(ltrim($databasePath, '/')) : '';

if ($databaseName === '' || !str_ends_with($databaseName, '_test')) {
    throw new RuntimeException(sprintf(
        'Unsafe TEST_DATABASE_URL: expected a database name ending in "_test", got "%s". PHPUnit has been stopped before booting the application.',
        $databaseName !== '' ? $databaseName : '(unknown)',
    ));
}

putenv('DATABASE_URL='.$testDatabaseUrl);
$_ENV['DATABASE_URL'] = $testDatabaseUrl;
$_SERVER['DATABASE_URL'] = $testDatabaseUrl;
