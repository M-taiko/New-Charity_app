<?php

declare(strict_types=1);

// Inspect a custody row + its money flows. Usage: php custody2_check.php <id>

$id = (int) ($argv[1] ?? 2);
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

$c = $pdo->query('SELECT * FROM custodies WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
echo "custody #{$id}:\n" . json_encode($c, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

if ($c) {
    echo 'getRemainingBalance inputs: amount=' . $c['amount']
        . ' spent=' . $c['spent']
        . ' returned=' . $c['returned']
        . ' pending_transfer_out=' . ($c['pending_transfer_out'] ?? '0')
        . "\n";
    $remain = (float) $c['amount'] - (float) $c['spent'] - (float) $c['returned'] - (float) ($c['pending_transfer_out'] ?? 0);
    echo "computed remaining: {$remain}\n\n";
}

echo "expenses on custody #{$id}: " . $pdo->query('SELECT COUNT(*) FROM expenses WHERE custody_id = ' . $id)->fetchColumn() . "\n";
echo "transfers out: " . json_encode($pdo->query('SELECT id, amount, status, to_agent_id FROM custody_transfers WHERE custody_id = ' . $id)->fetchAll(PDO::FETCH_ASSOC)) . "\n";
echo "return requests: " . json_encode($pdo->query('SELECT id, amount, status FROM custody_return_requests WHERE custody_id = ' . $id)->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n";
