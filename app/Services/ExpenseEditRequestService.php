<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseEditRequest;
use App\Models\Notification;
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
            // حفظ البيانات الأصلية
            $originalData = [
                'amount' => $expense->amount,
                'description' => $expense->description,
                'location' => $expense->location,
                'expense_category_id' => $expense->expense_category_id,
                'expense_item_id' => $expense->expense_item_id,
                'social_case_id' => $expense->social_case_id,
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

            // تعديل المبلغ (إذا كان هناك تغيير)
            if (isset($editRequest->requested_changes['amount'])) {
                $oldAmount = $expense->amount;
                $newAmount = $editRequest->requested_changes['amount'];
                $difference = $newAmount - $oldAmount;

                $changesToApply['amount'] = $newAmount;

                // إذا كان المصروف مرتبطاً بعهدة، نعدل الـ spent
                if ($expense->custody_id && $difference !== 0) {
                    $custody = $expense->custody;
                    if ($difference > 0) {
                        // إضافة مبلغ إلى spent
                        $custody->increment('spent', $difference);
                    } else {
                        // تقليل مبلغ من spent
                        $custody->decrement('spent', abs($difference));
                    }
                }
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
