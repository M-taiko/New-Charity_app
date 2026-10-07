<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\TreasuryTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ربط رجعي لحركات مصروفات الخزينة القديمة بمصاريفها (expense_id).
 *
 * بعض حركات سبتمبر المبكرة سُجلت قبل إضافة عمود expense_id فبقيت بلا ربط،
 * رغم أن المبالغ محسوبة صحيحة في كل التقارير. هذا الأمر يربطها بالمطابقة:
 *   1) مطابقة مباشرة: نفس العهدة + نفس المبلغ + قرب زمني (10 دقائق)
 *   2) مجموعات: صفوف بنفس الطابع الزمني مجموعها = مبلغ مصروف
 *   3) مصاريف المشاركة (pivot): صف لكل حصة (العهدة + مبلغ الحصة)
 *   4) تطابق المبلغ + الطابع الزمني بالثانية (سجلات قديمة بحقل عهدة مختلف)
 *
 * Usage:
 *   php artisan expenses:backfill-links            (dry-run — عرض فقط)
 *   php artisan expenses:backfill-links --apply    (تنفيذ فعلي)
 */
class BackfillExpenseLinks extends Command
{
    protected $signature = 'expenses:backfill-links {--apply : تنفيذ الربط فعلياً (الافتراضي dry-run)}';
    protected $description = 'ربط حركات مصروفات الخزينة القديمة بمصاريفها عبر expense_id (dry-run افتراضياً)';

    private const WINDOW = 600; // نافذة قرب الزمن بالثواني

    public function handle(): int
    {
        $rows = TreasuryTransaction::where('type', 'expense')->whereNull('expense_id')
            ->orderBy('transaction_date')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'custody_id' => $r->custody_id,
                'amount' => round((float) $r->amount, 2),
                'date' => $r->transaction_date->getTimestamp(),
            ])->all();

        if (empty($rows)) {
            $this->info('لا توجد حركات مصروفات بلا ربط — لا شيء لعمله.');
            return self::SUCCESS;
        }

        $claimedExpenseIds = TreasuryTransaction::whereNotNull('expense_id')->pluck('expense_id')->all();
        $expenses = Expense::whereNotIn('id', $claimedExpenseIds ?: [0])
            ->orderBy('created_at')->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'custody_id' => $e->custody_id,
                'amount' => round((float) $e->amount, 2),
                'date' => $e->created_at->getTimestamp(),
            ])->all();

        $links = [];
        $usedExpenses = [];

        // (1) مطابقة مباشرة: نفس العهدة + المبلغ + الأقرب زمنياً داخل النافذة
        foreach ($rows as $row) {
            if (isset($links[$row['id']])) {
                continue;
            }
            $best = null;
            foreach ($expenses as $exp) {
                if (isset($usedExpenses[$exp['id']])) {
                    continue;
                }
                if ($exp['custody_id'] !== $row['custody_id'] || $exp['amount'] !== $row['amount']) {
                    continue;
                }
                $diff = abs($exp['date'] - $row['date']);
                if ($diff <= self::WINDOW && ($best === null || $diff < $best['diff'])) {
                    $best = ['exp' => $exp, 'diff' => $diff];
                }
            }
            if ($best) {
                $links[$row['id']] = $best['exp']['id'];
                $usedExpenses[$best['exp']['id']] = true;
            }
        }

        // (2) مجموعات صفوف بنفس (العهدة + الطابع الزمني) مجموعها = مبلغ مصروف
        $groups = [];
        foreach ($rows as $row) {
            if (!isset($links[$row['id']])) {
                $groups[$row['custody_id'] . '|' . $row['date']][] = $row;
            }
        }
        foreach ($groups as $key => $group) {
            [$custodyId, $date] = explode('|', $key);
            $sum = round(array_sum(array_column($group, 'amount')), 2);
            foreach ($expenses as $exp) {
                if (isset($usedExpenses[$exp['id']])) {
                    continue;
                }
                if ($exp['custody_id'] != $custodyId || $exp['amount'] != $sum) {
                    continue;
                }
                if (abs($exp['date'] - $date) > self::WINDOW) {
                    continue;
                }
                foreach ($group as $row) {
                    $links[$row['id']] = $exp['id'];
                }
                $usedExpenses[$exp['id']] = true;
                break;
            }
        }

        // (3) حصص مصاريف المشاركة (expense_custody): صف لكل حصة
        $pivots = DB::table('expense_custody as ec')
            ->join('expenses as e', 'e.id', '=', 'ec.expense_id')
            ->whereNotIn('ec.expense_id', array_keys($usedExpenses) ?: [0])
            ->orderBy('e.created_at')
            ->get(['ec.expense_id', 'ec.custody_id', 'ec.amount', 'e.created_at']);
        foreach ($pivots as $pv) {
            $best = null;
            foreach ($rows as $row) {
                if (isset($links[$row['id']])) {
                    continue;
                }
                if ($row['custody_id'] != $pv->custody_id || $row['amount'] != round((float) $pv->amount, 2)) {
                    continue;
                }
                $diff = abs(strtotime($pv->created_at) - $row['date']);
                if ($diff <= self::WINDOW && ($best === null || $diff < $best['diff'])) {
                    $best = ['row' => $row, 'diff' => $diff];
                }
            }
            if ($best) {
                $links[$best['row']['id']] = $pv->expense_id;
                $usedExpenses[$pv->expense_id] = true;
            }
        }

        // (4) نفس المبلغ + نفس الطابع الزمني بالثانية بغض النظر عن حقل العهدة
        foreach ($rows as $row) {
            if (isset($links[$row['id']])) {
                continue;
            }
            foreach ($expenses as $exp) {
                if (isset($usedExpenses[$exp['id']])) {
                    continue;
                }
                if ($exp['amount'] === $row['amount'] && $exp['date'] === $row['date']) {
                    $links[$row['id']] = $exp['id'];
                    $usedExpenses[$exp['id']] = true;
                    break;
                }
            }
        }

        // تقرير
        $rowsSum = round(array_sum(array_column($rows, 'amount')), 2);
        $linkedSum = 0;
        foreach ($rows as $r) {
            if (isset($links[$r['id']])) {
                $linkedSum += $r['amount'];
            }
        }
        $this->info("حركات بلا ربط: " . count($rows) . " (مجموع {$rowsSum})");
        $this->info("سيتم ربط: " . count($links) . " حركة (مجموع {$linkedSum}) بعدد " . count($usedExpenses) . " مصروف");

        $leftRows = array_filter($rows, fn ($r) => !isset($links[$r['id']]));
        foreach ($leftRows as $r) {
            $this->warn("  - بلا مطابقة: حركة #{$r['id']} عهدة {$r['custody_id']} مبلغ {$r['amount']}");
        }

        if (!$links) {
            $this->info('لا مطابقات ممكنة.');
            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->newLine();
            $this->line('Dry-run — للتشغيل الفعلي: php artisan expenses:backfill-links --apply');
            foreach ($links as $txId => $expId) {
                $this->line("  حركة #{$txId} → مصروف #{$expId}");
            }
            return self::SUCCESS;
        }

        foreach ($links as $txId => $expId) {
            TreasuryTransaction::where('id', $txId)->whereNull('expense_id')->update(['expense_id' => $expId]);
        }
        $this->info("تم ربط " . count($links) . " حركة فعلياً.");
        return self::SUCCESS;
    }
}
