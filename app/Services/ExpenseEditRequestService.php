<?php

namespace App\Services;

use App\Models\Custody;
use App\Models\Expense;
use App\Models\ExpenseEditRequest;
use App\Models\Notification;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExpenseEditRequestService
{
    /**
     * طلب تعديل المصروف من المندوب
     */
    public function requestEdit(Expense $expense, array $changes, User $requester)
    {
        return DB::transaction(function () use ($expense, $changes, $requester) {
            // حفظ البيانات الأصلية (مع الأسماء والمسارات الكاملة للعرض)
            $originalData = [
                'amount' => $expense->amount,
                'description' => $expense->description,
                'location' => $expense->location,
                'expense_category_id' => $expense->expense_category_id,
                'expense_category_name' => $expense->category?->name,
                'expense_category_path' => $expense->category?->full_path,
                'expense_item_id' => $expense->expense_item_id,
                'expense_item_name' => $expense->item?->name,
                'social_case_id' => $expense->social_case_id,
                'social_case_name' => $expense->socialCase?->name,
                'attachment' => $expense->attachment,
            ];

            // إنشاء طلب التعديل
            $editRequest = ExpenseEditRequest::create([
                'expense_id' => $expense->id,
                'requested_by' => $requester->id,
                'original_data' => $originalData,
                'requested_changes' => $changes,
                'status' => 'pending',
            ]);

            // تغيير حالة المصروف
            $expense->update(['approval_status' => 'pending_edit']);

            // إرسال إشعارات للمحاسب والمدير
            $this->notifyReviewers($expense, $requester);

            return $editRequest;
        });
    }

    /**
     * الموافقة على طلب التعديل
     */
    public function approveEdit(ExpenseEditRequest $editRequest, User $reviewer)
    {
        return DB::transaction(function () use ($editRequest, $reviewer) {
            $expense = $editRequest->expense;

            // تطبيق التغييرات
            $changesToApply = [];

            // الحقول البسيطة
            foreach (['description', 'location', 'expense_category_id', 'expense_item_id', 'social_case_id', 'attachment'] as $field) {
                if (isset($editRequest->requested_changes[$field])) {
                    $changesToApply[$field] = $editRequest->requested_changes[$field];
                }
            }

            // بنود المصروف: array_key_exists حتى يعمل "الفراغ يمسح" (قيمة null مقصودة)
            if (array_key_exists('line_items', $editRequest->requested_changes ?? [])) {
                $changesToApply['line_items'] = $editRequest->requested_changes['line_items'];
            }

            // تعديل المبلغ (إذا كان هناك تغيير) — بنفس قواعد T6 في ExpenseController::update
            if (isset($editRequest->requested_changes['amount'])) {
                // مقارنة المبالغ بعد التقريب لخانتين عشريتين (لا مقارنة floats مباشرة)
                $oldAmount = round((float) $expense->amount, 2);
                $newAmount = round((float) $editRequest->requested_changes['amount'], 2);
                $amountChanged = $oldAmount !== $newAmount;

                // عدد صفوف pivot للمصروف (التوزيع على العهد)
                $pivotCustodies = $expense->custodies()->get();

                // (D2) مصروف موزع على أكثر من عهدة + تغيير المبلغ = رفض الموافقة
                if ($amountChanged && $pivotCustodies->count() > 1) {
                    throw new \Exception('لا يمكن الموافقة على تعديل مبلغ مصروف موزّع على أكثر من عهدة. يمكن رفض طلب التعديل أو تعديل باقي الحقول فقط دون تغيير المبلغ.');
                }

                // العهدة المتأثرة فعلياً: صف pivot واحد إن وُجد، وإلا custody_id
                $affectedCustodyId = $pivotCustodies->count() === 1
                    ? $pivotCustodies->first()->id
                    : $expense->custody_id;

                if ($affectedCustodyId && $amountChanged) {
                    $diff = round($newAmount - $oldAmount, 2);

                    // قفل العهدة المتأثرة داخل المعاملة
                    $custody = Custody::where('id', $affectedCustodyId)->lockForUpdate()->first();
                    if (!$custody) {
                        throw new \Exception('العهدة المرتبطة بالمصروف غير موجودة');
                    }

                    if ($diff > 0) {
                        // حارس صريح لحالة العهدة: لا زيادة على عهدة مرفوضة/ملغاة/معلقة
                        if (!in_array($custody->status, ['accepted', 'active', 'partially_returned'])) {
                            throw new \Exception('لا يمكن زيادة مبلغ مصروف مرتبط بعهدة في حالة "' . $custody->status . '" (عهدة #' . $custody->id . ')');
                        }

                        $remaining = round((float) $custody->getRemainingBalance(), 2);
                        if ($diff > $remaining) {
                            throw new \Exception('الزيادة المطلوبة في المبلغ (' . number_format($diff, 2) . ' ج.م) تتجاوز الرصيد المتبقي للعهدة #' . $custody->id . ' (' . number_format($remaining, 2) . ' ج.م)');
                        }
                    }

                    // تطبيق الفرق على spent
                    $custody->increment('spent', $diff);

                    // تحديث مبلغ صف pivot إن وُجد
                    if ($pivotCustodies->count() === 1) {
                        $expense->custodies()->updateExistingPivot($custody->id, ['amount' => $newAmount]);
                    }

                    // تحديث حركة الخزينة المرتبطة إن وُجدت (المصروفات الجديدة بعد T13)
                    $transaction = TreasuryTransaction::where('expense_id', $expense->id)->first();
                    if ($transaction) {
                        $transaction->update(['amount' => $newAmount]);
                    }

                    // إغلاق العهدة إذا وصل رصيدها للصفر (لا يتم فتح عهدة مغلقة تلقائياً)
                    if ($custody->fresh()->getRemainingBalance() <= 0 && $custody->status !== 'closed') {
                        $custody->update(['status' => 'closed']);
                        TreasuryTransaction::create([
                            'treasury_id' => $custody->treasury_id,
                            'type' => 'custody_close',
                            'amount' => 0,
                            'description' => "إقفال عهدة #$custody->id للمندوب {$custody->agent->name} (رصيد صفر)",
                            'user_id' => $reviewer->id,
                            'custody_id' => $custody->id,
                            'transaction_date' => now(),
                        ]);
                    }
                }

                $changesToApply['amount'] = $newAmount;
            }

            // تطبيق التغييرات على المصروف
            $expense->update($changesToApply);

            // تحديث طلب التعديل
            $editRequest->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            // تغيير حالة المصروف إلى معتمد
            $expense->update(['approval_status' => 'approved']);

            // إرسال إشعار للمندوب
            $this->notifyRequesterApproved($editRequest, $reviewer);

            return $editRequest;
        });
    }

    /**
     * رفض طلب التعديل
     */
    public function rejectEdit(ExpenseEditRequest $editRequest, User $reviewer, string $reason)
    {
        return DB::transaction(function () use ($editRequest, $reviewer, $reason) {
            $expense = $editRequest->expense;

            // تحديث طلب التعديل
            $editRequest->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // إعادة حالة المصروف إلى عادية (بدون تعديل معلق)
            $expense->update(['approval_status' => null]);

            // إرسال إشعار للمندوب
            $this->notifyRequesterRejected($editRequest, $reviewer, $reason);

            return $editRequest;
        });
    }

    /**
     * إرسال إشعارات للمحاسب والمدير
     */
    private function notifyReviewers(Expense $expense, User $requester)
    {
        // جلب المحاسب والمدير
        $reviewers = User::role(['محاسب', 'مدير'])->get();

        foreach ($reviewers as $reviewer) {
            Notification::create([
                'user_id' => $reviewer->id,
                'title' => 'طلب تعديل على مصروف',
                'message' => "المندوب {$requester->name} يطلب تعديل على المصروف #{$expense->id} بقيمة {$expense->amount} ج.م",
                'type' => 'warning',
                'related_id' => $expense->id,
                'related_type' => 'expense',
            ]);
        }
    }

    /**
     * إشعار المندوب بالموافقة
     */
    private function notifyRequesterApproved(ExpenseEditRequest $editRequest, User $reviewer)
    {
        Notification::create([
            'user_id' => $editRequest->requested_by,
            'title' => 'تم الموافقة على تعديل المصروف',
            'message' => "وافق {$reviewer->name} على تعديل المصروف #{$editRequest->expense_id}. تم تطبيق التغييرات.",
            'type' => 'success',
            'related_id' => $editRequest->expense_id,
            'related_type' => 'expense',
        ]);
    }

    /**
     * إشعار المندوب برفض التعديل
     */
    private function notifyRequesterRejected(ExpenseEditRequest $editRequest, User $reviewer, string $reason)
    {
        Notification::create([
            'user_id' => $editRequest->requested_by,
            'title' => 'تم رفض طلب التعديل',
            'message' => "رفض {$reviewer->name} طلب التعديل على المصروف #{$editRequest->expense_id}. السبب: {$reason}",
            'type' => 'error',
            'related_id' => $editRequest->expense_id,
            'related_type' => 'expense',
        ]);
    }
}
