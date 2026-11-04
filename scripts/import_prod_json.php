<?php

declare(strict_types=1);

/**
 * Import a phpMyAdmin JSON export into the local database.
 *
 * Usage: php import_prod_json.php "<path-to-json>"
 *
 * - Replaces ALL rows in every table present in the JSON (except `migrations`).
 * - FK checks disabled during import so insert order does not matter.
 * - Values arrive as strings/null from phpMyAdmin JSON; MySQL coerces them
 *   to the column types, which matches the source DB exactly.
 */

if ($argc < 2) {
    fwrite(STDERR, "usage: php import_prod_json.php <path-to-json>\n");
    exit(1);
}
$jsonPath = $argv[1];
if (!is_file($jsonPath)) {
    fwrite(STDERR, "file not found: {$jsonPath}\n");
    exit(1);
}

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

$raw = file_get_contents($jsonPath);
$entries = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($entries)) {
    fwrite(STDERR, "unexpected JSON structure\n");
    exit(1);
}

// skip schema bookkeeping — local migrations table stays as-is
const SKIP_TABLES = ['migrations'];

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('SET UNIQUE_CHECKS=0');

$imported = 0;
foreach ($entries as $entry) {
    if (($entry['type'] ?? null) !== 'table') {
        continue;
    }
    $table = $entry['name'];
    if (in_array($table, SKIP_TABLES, true)) {
        echo "skip   {$table} (kept local)\n";
        continue;
    }
    $rows = $entry['data'] ?? [];
    $safeTable = '`' . str_replace('`', '``', $table) . '`';

    $pdo->exec("TRUNCATE TABLE {$safeTable}");

    if ($rows === []) {
        echo "ok     {$table}: 0 rows\n";
        continue;
    }

    $columns = array_keys($rows[0]);
    $colList = implode(', ', array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', $columns));

    $chunkSize = (int) max(1, floor(2000 / max(1, count($columns)))); // stay well under placeholder limits
    $inserted = 0;
    foreach (array_chunk($rows, $chunkSize) as $chunk) {
        $placeholders = [];
        $values = [];
        foreach ($chunk as $row) {
            $placeholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            foreach ($columns as $c) {
                $values[] = $row[$c] ?? null;
            }
        }
        $sql = "INSERT INTO {$safeTable} ({$colList}) VALUES " . implode(', ', $placeholders);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $inserted += count($chunk);
    }
    $imported += $inserted;
    echo "ok     {$table}: {$inserted} rows\n";
}

$pdo->exec('SET UNIQUE_CHECKS=1');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

echo "\ndone — {$imported} rows imported\n";
