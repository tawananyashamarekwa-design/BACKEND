<?php

$databaseUrl = getenv('DATABASE_URL');
$driver = getenv('DB_DRIVER') ?: (getenv('DB_PASSWORD') ? 'pgsql' : 'mysql');
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
$dbname = getenv('DB_NAME') ?: 'electronics_ecommerce';
$username = getenv('DB_USER') ?: 'root';
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

$dsn = $driver === 'pgsql'
    ? "pgsql:host=$host;port=$port;dbname=$dbname"
    : "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}
