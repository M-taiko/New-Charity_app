<?php

declare(strict_types=1);

// Find custodies that are closed but still hold money (remaining > 0),
// i.e. rows hit by the flawed `returned >= amount` closure rule.

require __DIR__ . '/../vendor/autoload.php';

$env = [];
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], " \t\n\r\0\x0B\"'");
    }
}
$capsule = new Illuminate\Database\Capsule\Manager();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $env['DB_HOST'],
    'port' => $env['DB_PORT'],
    'database' => $env['DB_DATABASE'],
    'username' => $env['DB_USERNAME'],
    'password' => $env['DB_PASSWORD'],
    'charset' => 'utf8mb4',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

foreach (App\Models\Custody::where('status', 'closed')->get() as $c) {
    $remaining = round((float) $c->getRemainingBalance(), 2);
    if ($remaining > 0) {
        echo sprintf(
            "#%d agent=%s amount=%s transferred_in=%s spent=%s transferred_out=%s returned=%s REMAINING=%.2f\n",
            $c->id,
            $c->agent?->name ?? $c->agent_id,
            $c->amount,
            $c->transferred_in,
            $c->spent,
            $c->transferred_out,
            $c->returned,
            $remaining
        );
    }
}
echo "scan done\n";
