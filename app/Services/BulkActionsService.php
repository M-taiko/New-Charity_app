<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\SocialCase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * N-04: the bulk actions. Every id is judged and changed on its own, under
 * the rule of the single action it repeats — so one refused id never stops
 * the others, and each record's own side effects (the agent is told, the
 * activity log) happen exactly as they do one by one.
 */
final class BulkActionsService
{
    /** the most records one bulk action may name */
    public const MAX_IDS = 100;

    /** one record's outcome */
    public const DONE = 'done';
    public const REFUSED = 'refused';

    /**
     * Review (or unreview) expenses. Reviewing is for an accountant or a
     * manager, unreviewing for a manager only — as the single endpoints are.
     *
     * @param  list<int>  $ids
     * @return array{results: list<array{id: int, status: string, code?: string, message?: string}>, done: int, refused: int, amount: string}
     *
     * @throws AuthorizationException the user may not do this action at all
     */
    public function reviewExpenses(User $user, array $ids, bool $unreview): array
    {
        if ($unreview) {
            $this->authorize($user->hasRole('مدير'), 'فقط المدير يمكنه إلغاء المراجعة');
        } else {
            $this->authorize($user->hasRole('محاسب') || $user->hasRole('مدير'), 'فقط المحاسب أو المدير يمكنه مراجعة المصروفات');
        }

        $results = [];
        $done = 0;
        $refused = 0;
        $cents = 0;
        foreach ($ids as $id) {
            /** @var Expense|null $expense */
            $expense = Expense::find($id);
            $refusal = $this->expenseRefusal($user, $expense, $unreview);
            if ($refusal !== null) {
                $results[] = ['id' => $id, 'status' => self::REFUSED, 'code' => $refusal['code'], 'message' => $refusal['message']];
                $refused++;
                continue;
            }
            /** @var Expense $current the refusal above is null only for an expense that exists */
            $current = $expense;
            // one record at a time: a failure on one leaves the others as they were made
            DB::transaction(function () use ($user, $current, $unreview): void {
                $current->update($unreview
                    ? ['reviewed_by' => null, 'reviewed_at' => null]
                    : ['reviewed_by' => $user->id, 'reviewed_at' => now(), 'is_quick_expense' => false]);

                NotificationService::notifyUser(
                    $current->user_id,
                    $unreview ? 'تم فتح التعديل على مصروف' : 'تمت مراجعة مصروفك',
                    $unreview
                        ? 'قام ' . $user->name . ' بإلغاء مراجعة المصروف رقم #' . $current->id . ' - أصبح التعديل متاحاً مرة أخرى'
                        : 'قام ' . $user->name . ' بمراجعة المصروف رقم #' . $current->id . '. أي تعديل تطلبه بعد المراجعة سينتظر موافقة المحاسب/المدير',
                    $unreview ? 'warning' : 'info',
                    $current->id,
                    'expense'
                );
                ActivityLogService::log(
                    $unreview ? 'updated' : 'reviewed',
                    $unreview
                        ? 'قام ' . $user->name . ' بإلغاء مراجعة المصروف #' . $current->id . ' (إجراء جماعي)'
                        : 'قام ' . $user->name . ' بمراجعة المصروف #' . $current->id . ' (إجراء جماعي)',
                    $current,
                    ['bulk' => true],
                    $user->id
                );
            });

            $results[] = ['id' => $id, 'status' => self::DONE];
            $done++;
            // the total is counted in whole piasters, read off the decimal string: never through a float
            $cents += $this->toCents((string) $current->amount);
        }

        return ['results' => $results, 'done' => $done, 'refused' => $refused, 'amount' => $this->fromCents($cents)];
    }

    /**
     * Activate (or deactivate) social cases — for whoever manages cases, as
     * the single endpoint is; and a researcher only their own cases: a bulk
     * change is wider than one click, so each case is checked against its owner.
     *
     * @param  list<int>  $ids
     * @return array{results: list<array{id: int, status: string, code?: string, message?: string}>, done: int, refused: int, amount: string}
     *
     * @throws AuthorizationException
     */
    public function setCasesActive(User $user, array $ids, bool $active): array
    {
        $this->authorize($user->can('manage_social_cases'), 'ليس لديك صلاحية تعديل الحالات');

        $results = [];
        $done = 0;
        $refused = 0;
        $isResearcher = $user->hasRole('باحث اجتماعي') && ! $user->hasRole('محاسب') && ! $user->hasRole('مدير');
        foreach ($ids as $id) {
            /** @var SocialCase|null $case */
            $case = SocialCase::find($id);
            if ($case === null) {
                $results[] = $this->refused($id, 'social_case.not_found', 'الحالة غير موجودة');
                $refused++;
                continue;
            }
            if ($isResearcher && (int) $case->researcher_id !== (int) $user->id) {
                $results[] = $this->refused($id, 'social_case.not_yours', 'ليست حالة تخصك');
                $refused++;
                continue;
            }
            if ((bool) $case->is_active === $active) {
                $results[] = $this->refused($id, $active ? 'social_case.already_active' : 'social_case.already_inactive', $active ? 'الحالة نشطة أصلاً' : 'الحالة متوقفة أصلاً');
                $refused++;
                continue;
            }

            $current = $case;
            DB::transaction(function () use ($user, $current, $active): void {
                $current->update(['is_active' => $active]);
                ActivityLogService::log(
                    'updated',
                    'قام ' . $user->name . ' ب' . ($active ? 'تنشيط' : 'إيقاف') . ' الحالة #' . $current->id . ' (إجراء جماعي)',
                    $current,
                    ['bulk' => true, 'is_active' => $active],
                    $user->id
                );
            });

            $results[] = ['id' => $id, 'status' => self::DONE];
            $done++;
        }

        return ['results' => $results, 'done' => $done, 'refused' => $refused, 'amount' => '0.00'];
    }

    /**
     * Why this user may not do this review action on this expense; null when they may.
     *
     * @return array{code: string, message: string}|null
     */
    private function expenseRefusal(User $user, ?Expense $expense, bool $unreview): ?array
    {
        if ($expense === null) {
            return ['code' => 'expense.not_found', 'message' => 'المصروف غير موجود'];
        }
        // the rule of the lists: the management lists see everything, everyone else their own
        if (! $user->can('view_all_expenses') && (int) $expense->user_id !== (int) $user->id) {
            return ['code' => 'expense.forbidden', 'message' => 'ليس مصروفًا تخصك'];
        }
        if (! $unreview && $expense->isReviewed()) {
            return ['code' => 'expense.already_reviewed', 'message' => 'المصروف مراجَع أصلاً'];
        }
        if ($unreview && ! $expense->isReviewed()) {
            return ['code' => 'expense.not_reviewed', 'message' => 'المصروف غير مراجَع أصلاً'];
        }

        return null;
    }

    /** @return array{id: int, status: string, code: string, message: string} */
    private function refused(int $id, string $code, string $message): array
    {
        return ['id' => $id, 'status' => self::REFUSED, 'code' => $code, 'message' => $message];
    }

    /** a two-decimal amount ("1234.56") as whole piasters, read as text */
    private function toCents(string $decimal): int
    {
        $sign = str_starts_with($decimal, '-') ? -1 : 1;
        $parts = explode('.', ltrim($decimal, '-'));
        $piasters = isset($parts[1]) ? (int) str_pad(substr($parts[1], 0, 2), 2, '0', STR_PAD_RIGHT) : 0;

        return $sign * (((int) $parts[0]) * 100 + $piasters);
    }

    /** whole piasters back to a two-decimal amount */
    private function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** @throws AuthorizationException */
    private function authorize(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw new AuthorizationException($message);
        }
    }
}
