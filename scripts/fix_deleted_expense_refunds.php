<?php

declare(strict_types=1);

/**
 * إصلاح آثار حذف المصروفات قبل إصلاح الثغرة (سكريبت لمرة واحدة على البرودكشن).
 *
 * المشكلة: ExpenseController::destroy كان يحذف المصروف (soft-delete) دون عكس أثره
 * المالي — فيبقى نصيبه محسوباً داخل spent العهدة ولا يعود الرصيد، وتبقى إشعارات
 * تشير إلى مصروف محذوف (404 عند الضغط عليها).
 *
 * يعالج كل مصروف محذوف (deleted_at غير فارغ) ما زال له صفوف توزيع على العهدات:
 *   1. إرجاع نصيب كل عهدة: spent -= share
 *   2. إعادة فتح العهدة إن كانت closed باستنزاف وعاد لها رصيد (+ حركة custody_reopen للأثر)
 *   3. حذف صفوف expense_custody وحركات الخزينة type=expense المرتبطة بالمصروف
 *   4. حذف الإشعارات التي تشير إلى المصروف
 *   5. حذف طلبات التعديل المعلقة للمصروف (soft-delete)
 *
 * عهدة سُوِّت بإرجاع نقدي (returned > 0) لا تُلمس — تُعرض للمراجعة اليدوية فقط.
 *
 * Usage:
 *   php fix_deleted_expense_refunds.php            (dry-run — يعرض الخطة فقط)
 *   php fix_deleted_expense_refunds.php --execute  (ينفّذ فعلياً داخل transaction)
 */

$execute = in_array('--execute', $argv, true);

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

// المصروفات المحذوفة التي ما زال لها توزيع على العهدات (أي أن الحذف لم يُعكس أثره)
$expenses = $pdo->query(
    "SELECT e.id, e.amount, e.custody_id, e.source, e.deleted_at
     FROM expenses e
     WHERE e.deleted_at IS NOT NULL
       AND EXISTS (SELECT 1 FROM expense_custody ec WHERE ec.expense_id = e.id)
     ORDER BY e.id"
)->fetchAll(PDO::FETCH_ASSOC);

if (!$expenses) {
    echo "لا توجد مصروفات محذوفة تحتاج إصلاحاً — قاعدة البيانات سليمة\n";
    exit(0);
}

echo ($execute ? "وضع التنفيذ الفعلي\n" : "وضع المعاينة (dry-run) — أضف --execute للتنفيذ\n") . "\n";

$plan = function (string $msg): void {
    echo $msg . PHP_EOL;
};

if ($execute) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->beginTransaction();
}

try {
    foreach ($expenses as $expense) {
        $eid = (int) $expense['id'];
        $plan("── المصروف #{$eid} (amount={$expense['amount']}, source={$expense['source']}, deleted_at={$expense['deleted_at']})");

        $allocations = $pdo->query(
            "SELECT custody_id, amount FROM expense_custody WHERE expense_id = {$eid} ORDER BY custody_id"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allocations as $allocation) {
            $cid = (int) $allocation['custody_id'];
            $share = round((float) $allocation['amount'], 2);

            $c = $pdo->query("SELECT * FROM custodies WHERE id = {$cid} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
            if (!$c) {
                $plan("   ⚠ عهدة #{$cid} غير موجودة — يتخطى التوزيع");
                continue;
            }
            if ((float) $c['returned'] > 0) {
                $plan("   ⚠ عهدة #{$cid} سُوِّت بإرجاع نقدي (returned={$c['returned']}) — تحتاج مراجعة يدوية، لن تُلمس");
                continue;
            }

            $newSpent = round((float) $c['spent'] - $share, 2);
            $plan("   عهدة #{$cid}: spent {$c['spent']} → {$newSpent} (استرجاع {$share})");

            // الرصيد بعد الاسترجاع وفق getRemainingBalance في موديل Custody
            $remaining = round(
                (float) $c['amount'] + (float) $c['transferred_in'] - $newSpent
                - (float) $c['transferred_out'] - (float) $c['returned']
                - (float) $c['pending_return'] - (float) $c['pending_transfer_out'],
                2
            );

            $reopen = ($c['status'] === 'closed' && $remaining > 0);
            if ($reopen) {
                $newStatus = !empty($c['received_at']) ? 'active' : 'accepted';
                $plan("   عهدة #{$cid}: status closed → {$newStatus} (رصيد بعد الاسترجاع: {$remaining})");
            }

            if ($execute) {
                $pdo->prepare("UPDATE custodies SET spent = ? WHERE id = ?")
                    ->execute([number_format($newSpent, 2, '.', ''), $cid]);

                if ($reopen) {
                    $pdo->prepare("UPDATE custodies SET status = ? WHERE id = ?")->execute([$newStatus, $cid]);
                    $pdo->prepare(
                        "INSERT INTO treasury_transactions
                            (treasury_id, type, amount, description, user_id, custody_id, transaction_date, created_at, updated_at)
                         VALUES (?, 'custody_reopen', 0, ?, NULL, ?, NOW(), NOW(), NOW())"
                    )->execute([
                        $c['treasury_id'],
                        "إعادة فتح عهدة #{$cid} للمندوب بعد حذف المصروف #{$eid} (رصيد متبقٍ: " . number_format($remaining, 2, '.', '') . ' ج.م)',
                        $cid,
                    ]);
                }
            }
        }

        // حركة الخزينة الخاصة بالمصروف لم تعد تعبر عن حقيقة
        $txnCount = $pdo->query(
            "SELECT COUNT(*) FROM treasury_transactions WHERE expense_id = {$eid} AND type = 'expense'"
        )->fetchColumn();
        $plan("   حذف حركات الخزينة type=expense المرتبطة: {$txnCount} صف");

        // إشعارات تشير إلى مصروف محذوف (سبب الـ 404)
        $notifCount = $pdo->query(
            "SELECT COUNT(*) FROM notifications WHERE related_id = {$eid} AND related_type IN ('expense', 'Expense')"
        )->fetchColumn();
        $plan("   حذف الإشعارات المرتبطة: {$notifCount} صف");

        // طلبات تعديل معلقة على مصروف محذوف
        $editCount = $pdo->query(
            "SELECT COUNT(*) FROM expense_edit_requests WHERE expense_id = {$eid} AND status = 'pending' AND deleted_at IS NULL"
        )->fetchColumn();
        $plan("   حذف طلبات التعديل المعلقة: {$editCount} صف");

        if ($execute) {
            $pdo->exec("DELETE FROM expense_custody WHERE expense_id = {$eid}");
            $pdo->exec("DELETE FROM treasury_transactions WHERE expense_id = {$eid} AND type = 'expense'");
            $pdo->exec("DELETE FROM notifications WHERE related_id = {$eid} AND related_type IN ('expense', 'Expense')");
            $pdo->exec("UPDATE expense_edit_requests SET deleted_at = NOW()
                        WHERE expense_id = {$eid} AND status = 'pending' AND deleted_at IS NULL");
        }
        $plan('');
    }

    if ($execute) {
        $pdo->commit();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        echo "تم التنفيذ بنجاح داخل transaction ✅\n";
    } else {
        echo "معاينة فقط — لم يُغيَّر أي صف. شغّل بـ --execute للتطبيق.\n";
    }
} catch (Throwable $e) {
    if ($execute && $pdo->inTransaction()) {
        $pdo->rollBack();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    fwrite(STDERR, 'فشل التنفيذ — تم التراجع الكامل: ' . $e->getMessage() . "\n");
    exit(1);
}
