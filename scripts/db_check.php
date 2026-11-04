<?php

declare(strict_types=1);

// Read .env manually (parse_ini_file chokes on unquoted values with spaces)
$env = [];
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], " \t\n\r\0\x0B\"'");
    }
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $env['DB_DATABASE']),
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
echo "connected to {$env['DB_DATABASE']} OK\n";

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'existing tables: ' . count($tables) . "\n";
foreach ($tables as $t) {
    $c = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $t) . '`')->fetchColumn();
    echo '  ' . $t . ': ' . $c . " rows\n";
}
