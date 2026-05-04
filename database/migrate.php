<?php

$databaseUrl = getenv('DATABASE_URL');
$driver = getenv('DB_DRIVER') ?: (
    str_starts_with($databaseUrl ?: '', 'postgres') || getenv('DB_PORT') === '5432'
        ? 'pgsql'
        : 'pgsql'
);
$host = getenv('DB_HOST') ?: 'dpg-d7rks5i8qa3s73dj1t40-a';
$port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
$dbname = getenv('DB_NAME') ?: 'backend_db_zzin';
$username = getenv('DB_USER') ?: 'backend_db_zzin_user';
$password = getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: '');

if ($databaseUrl) {
    $database = parse_url($databaseUrl);
    $driver = str_starts_with($database['scheme'] ?? '', 'postgres') ? 'pgsql' : 'mysql';
    $host = $database['host'] ?? $host;
    $port = $database['port'] ?? $port;
    $dbname = isset($database['path']) ? ltrim($database['path'], '/') : $dbname;
    $username = $database['user'] ?? $username;
    $password = $database['pass'] ?? $password;
}

$schemaPath = $driver === 'pgsql'
    ? __DIR__ . '/schema.postgres.sql'
    : __DIR__ . '/schema.sql';

$dsn = $driver === 'pgsql'
    ? "pgsql:host=$host;port=$port;dbname=$dbname"
    : "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException("Could not read schema file: $schemaPath");
    }

    $pdo->exec($schema);

    echo "Database migration completed using $schemaPath\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Database migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
