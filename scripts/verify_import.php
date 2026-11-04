<?php

declare(strict_types=1);

// Compare per-table row counts between the JSON export and the local DB.
// Usage: php verify_import.php "<path-to-json>"

$jsonPath = $argv[1] ?? '';
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

$entries = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
$mismatch = 0;
foreach ($entries as $e) {
    if (($e['type'] ?? null) !== 'table' || $e['name'] === 'migrations') {
        continue;
    }
    $db = (int) $pdo->query('SELECT COUNT(*) FROM `' . $e['name'] . '`')->fetchColumn();
    $json = count($e['data'] ?? []);
    if ($db !== $json) {
        echo "MISMATCH {$e['name']}: json={$json} db={$db}\n";
        $mismatch++;
    }
}
echo $mismatch === 0 ? "all table counts match JSON\n" : "{$mismatch} tables mismatched\n";

echo 'users: ' . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()
    . ' | custodies: ' . $pdo->query('SELECT COUNT(*) FROM custodies')->fetchColumn()
    . ' | pending transfers: ' . $pdo->query("SELECT COUNT(*) FROM custody_transfers WHERE status='pending'")->fetchColumn()
    . "\n";
echo 'sample users: ' . json_encode($pdo->query('SELECT id, name FROM users ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n";
