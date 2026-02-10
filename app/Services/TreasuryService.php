<?php

namespace App\Services;

use App\Models\Treasury;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\TreasuryTransaction;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function createCustody($treasuryId, $agentId, $accountantId, $amount, $notes = null, $isAgentRequest = false)
    {
        return DB::transaction(function () use ($treasuryId, $agentId, $accountantId, $amount, $notes, $isAgentRequest) {
            $custody = Custody::create([
                'treasury_id' => $treasuryId,
                'agent_id' => $agentId,
                'accountant_id' => $accountantId,
                'initiated_by' => $isAgentRequest ? 'agent' : 'accountant',
                'amount' => $amount,
                'spent' => 0,
                'returned' => 0,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            // Notifications based on request type
            if ($isAgentRequest) {
                // Agent request: notify managers and accountants for approval
                $agent = User::find($agentId);
                $this->notifyManagers(
                    'طلب عهدة جديد',
                    "المندوب {$agent->name} يطلب عهدة بقيمة {$amount} ج.م",
                    'warning',
                    $custody->id,
                    'custody'
                );
                $this->notifyAccountants(
                    'طلب عهدة جديد',
                    "المندوب {$agent->name} يطلب عهدة بقيمة {$amount} ج.م",
                    'warning',
                    $custody->id,
                    'custody'
                );
            } else {
                // Manager/Accountant created: notify agent that custody was assigned
                $this->notifyUser(
                    $agentId,
                    'عهدة جديدة تتطلب موافقتك',
                    "تم تخصيص عهدة لك بقيمة {$amount} ج.م. يرجى قبول أو رفض العهدة",
                    'warning',
                    $custody->id,
                    'custody'
                );
            }

            return $custody;
        });
    }

    public function acceptCustody($custody)
    {
        return DB::transaction(function () use ($custody) {
            // Ensure this is only for agent-initiated requests
            if ($custody->initiated_by !== 'agent') {
                throw new \Exception('هذه العملية متاحة فقط لطلبات العهد من المندوب');
            }

            // Check if treasury has sufficient balance
            $treasury = $custody->treasury;
            if ($treasury->balance < $custody->amount) {
                $agentName = $custody->agent->name ?? 'غير محدد';
                throw new \Exception(
                    "❌ لا يمكن الموافقة على عهدة المندوب {$agentName}\n\n" .
                    $this->insufficientBalanceError($treasury->balance, $custody->amount, 'الموافقة على العهدة')
                );
            }

            $custody->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            // Notify agent to acknowledge receipt
            $this->notifyUser(
                $custody->agent_id,
                'تمت الموافقة على عهدتك',
                "تم الموافقة على عهدتك بقيمة {$custody->amount} ج.م. يرجى تأكيد الاستقبال لصرف الفلوس",
                'info',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function receiveCustody($custody)
    {
        return DB::transaction(function () use ($custody) {
            if ($custody->status !== 'accepted') {
                throw new \Exception('العهدة يجب أن تكون في حالة مقبولة قبل الاستقبال');
            }

            $treasury = $custody->treasury;

            // Check if treasury has sufficient balance
            if ($treasury->balance < $custody->amount) {
                throw new \Exception(
                    "❌ لا يمكن صرف العهدة للمندوب\n\n" .
                    $this->insufficientBalanceError($treasury->balance, $custody->amount, 'صرف العهدة')
                );
            }

            // Deduct from treasury
            $treasury->decrement('balance', $custody->amount);

            // Update status and received timestamp
            $custody->update([
                'status' => 'active',
                'received_at' => now(),
            ]);

            // Create transaction record
            TreasuryTransaction::create([
                'treasury_id' => $custody->treasury_id,
                'type' => 'custody_out',
                'amount' => $custody->amount,
                'description' => "صرف عهدة للمندوب {$custody->agent->name}",
                'user_id' => $custody->agent_id,
                'custody_id' => $custody->id,
                'transaction_date' => now(),
            ]);

            // Notify agent that funds were transferred
            $this->notifyUser(
                $custody->agent_id,
                'تم صرف العهدة',
                "تم صرف عهدتك بقيمة {$custody->amount} ج.م. يمكنك الآن استخدام المبلغ",
                'success',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function rejectCustody($custody, $reason = null)
    {
        return DB::transaction(function () use ($custody, $reason) {
            // Ensure this is only for agent-initiated requests
            if ($custody->initiated_by !== 'agent') {
                throw new \Exception('هذه العملية متاحة فقط لطلبات العهد من المندوب');
            }

            $custody->update([
                'status' => 'rejected',
                'notes' => $reason,
            ]);

            // Notify agent
            $this->notifyUser(
                $custody->agent_id,
                'تم رفض العهدة',
                "تم رفض العهدة. السبب: {$reason}",
                'error',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function agentAcceptCustody($custody)
    {
        return DB::transaction(function () use ($custody) {
            // Validation
            if ($custody->initiated_by !== 'accountant') {
                throw new \Exception('هذه العملية متاحة فقط للعهد المرسلة من المحاسب');
            }
            if ($custody->status !== 'pending') {
                throw new \Exception('العهدة يجب أن تكون في حالة انتظار');
            }
            if ($custody->agent_id !== auth()->id()) {
                throw new \Exception('غير مصرح لك بقبول هذه العهدة');
            }

            // Check treasury balance
            $treasury = $custody->treasury;
            if ($treasury->balance < $custody->amount) {
                throw new \Exception(
                    $this->insufficientBalanceError($treasury->balance, $custody->amount, 'قبول العهدة')
                );
            }

            // Deduct from treasury immediately
            $treasury->decrement('balance', $custody->amount);

            // Update custody
            $custody->update([
                'status' => 'active',
                'accepted_at' => now(),
                'received_at' => now(),
            ]);

            // Create transaction record
            TreasuryTransaction::create([
                'treasury_id' => $custody->treasury_id,
                'type' => 'custody_out',
                'amount' => $custody->amount,
                'description' => "صرف عهدة للمندوب {$custody->agent->name} (قبول مباشر)",
                'user_id' => $custody->agent_id,
                'custody_id' => $custody->id,
                'transaction_date' => now(),
            ]);

            // Notify accountants and managers
            $this->notifyAccountants(
                'تم قبول العهدة',
                "المندوب {$custody->agent->name} قبل العهدة بقيمة {$custody->amount} ج.م وتم صرف الفلوس",
                'success',
                $custody->id,
                'custody'
            );
            $this->notifyManagers(
                'تم قبول العهدة',
                "المندوب {$custody->agent->name} قبل العهدة بقيمة {$custody->amount} ج.م وتم صرف الفلوس",
                'success',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function agentRejectCustody($custody, $reason = null)
    {
        return DB::transaction(function () use ($custody, $reason) {
            // Validation
            if ($custody->initiated_by !== 'accountant') {
                throw new \Exception('هذه العملية متاحة فقط للعهد المرسلة من المحاسب');
            }
            if ($custody->status !== 'pending') {
                throw new \Exception('العهدة يجب أن تكون في حالة انتظار');
            }
            if ($custody->agent_id !== auth()->id()) {
                throw new \Exception('غير مصرح لك برفض هذه العهدة');
            }

            $custody->update([
                'status' => 'rejected',
                'notes' => $reason ? "رفض من المندوب: {$reason}" : "رفض من المندوب",
            ]);

            // Notify accountants and managers
            $this->notifyAccountants(
                'رفض العهدة من المندوب',
                "المندوب {$custody->agent->name} رفض العهدة بقيمة {$custody->amount} ج.م. السبب: " . ($reason ?? 'غير محدد'),
                'error',
                $custody->id,
                'custody'
            );
            $this->notifyManagers(
                'رفض العهدة من المندوب',
                "المندوب {$custody->agent->name} رفض العهدة بقيمة {$custody->amount} ج.م. السبب: " . ($reason ?? 'غير محدد'),
                'error',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function recordExpense($custodyId, $userId, $amount, $type, $description, $location, $socialCaseId = null)
    {
        return DB::transaction(function () use ($custodyId, $userId, $amount, $type, $description, $location, $socialCaseId) {
            $custody = Custody::findOrFail($custodyId);

            if ($custody->getRemainingBalance() < $amount) {
                throw new \Exception('الرصيد غير كافي');
            }

            $expense = Expense::create([
                'custody_id' => $custodyId,
                'user_id' => $userId,
                'social_case_id' => $socialCaseId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'location' => $location,
                'expense_date' => now(),
            ]);

            // Update custody spent
            $custody->increment('spent', $amount);

            // Create transaction
            TreasuryTransaction::create([
                'treasury_id' => $custody->treasury_id,
                'type' => 'expense',
                'amount' => $amount,
                'description' => $description,
                'user_id' => $userId,
                'custody_id' => $custodyId,
                'transaction_date' => now(),
            ]);

            // Notify if large expense
            if ($amount > 1000) {
                $this->notifyManagers(
                    'مصروف كبير',
                    "عملية صرف بقيمة {$amount} ج.م من العهدة",
                    'warning',
                    $expense->id,
                    'expense'
                );
            }

            return $expense;
        });
    }

    public function requestReturnCustody($custody, $returnedAmount)
    {
        return DB::transaction(function () use ($custody, $returnedAmount) {
            // Store as pending return
            $custody->increment('pending_return', $returnedAmount);
            // Status remains as 'accepted' - it's just marked as pending return internally

            // Send notification to accountants and managers
            $this->notifyAccountants(
                'طلب رد عهدة',
                "المندوب {$custody->agent->name} يطلب إرجاع {$returnedAmount} ج.م من العهدة",
                'warning',
                $custody->id,
                'custody'
            );

            $this->notifyManagers(
                'طلب رد عهدة',
                "المندوب {$custody->agent->name} يطلب إرجاع {$returnedAmount} ج.م من العهدة",
                'warning',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function approveCustodyReturn($custody)
    {
        return DB::transaction(function () use ($custody) {
            $returnedAmount = $custody->pending_return;

            // Move from pending to confirmed returned
            $custody->update([
                'returned' => $custody->returned + $returnedAmount,
                'pending_return' => 0,
            ]);

            // Add to treasury
            $treasury = $custody->treasury;
            $treasury->increment('balance', $returnedAmount);

            // Create transaction
            TreasuryTransaction::create([
                'treasury_id' => $custody->treasury_id,
                'type' => 'custody_return',
                'amount' => $returnedAmount,
                'description' => "إرجاع عهدة من المندوب {$custody->agent->name}",
                'user_id' => auth()->id(),
                'custody_id' => $custody->id,
                'transaction_date' => now(),
            ]);

            // Update status
            $isClosed = $custody->returned >= $custody->amount;
            if ($isClosed) {
                $custody->update(['status' => 'closed']);
            } else {
                $custody->update(['status' => 'partially_returned']);
            }

            // Notify agent
            $statusMessage = $isClosed ? 'وتم إغلاق العهدة' : 'والعهدة مازالت نشطة';
            $this->notifyUser(
                $custody->agent_id,
                'تم قبول الرد',
                "تم قبول رد المبلغ {$returnedAmount} ج.م من العهدة {$statusMessage}",
                'success',
                $custody->id,
                'custody'
            );

            // Notify accountants
            $this->notifyAccountants(
                'تم قبول رد العهدة',
                "تم قبول رد المبلغ {$returnedAmount} ج.م من المندوب {$custody->agent->name} وإضافته للخزينة",
                'success',
                $custody->id,
                'custody'
            );

            // Notify managers
            $this->notifyManagers(
                'تم قبول رد العهدة',
                "تم قبول رد المبلغ {$returnedAmount} ج.م من المندوب {$custody->agent->name} وإضافته للخزينة",
                'success',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function returnCustody($custody, $returnedAmount)
    {
        return DB::transaction(function () use ($custody, $returnedAmount) {
            $custody->increment('returned', $returnedAmount);

            // Add to treasury
            $treasury = $custody->treasury;
            $treasury->increment('balance', $returnedAmount);

            // Create transaction
            TreasuryTransaction::create([
                'treasury_id' => $custody->treasury_id,
                'type' => 'custody_return',
                'amount' => $returnedAmount,
                'description' => "إرجاع عهدة من المندوب {$custody->agent->name}",
                'user_id' => auth()->id(),
                'custody_id' => $custody->id,
                'transaction_date' => now(),
            ]);

            // Update status if fully returned
            if ($custody->returned >= $custody->amount) {
                $custody->update(['status' => 'closed']);
            } elseif ($custody->returned > 0) {
                $custody->update(['status' => 'partially_returned']);
            }

            return $custody;
        });
    }

    public function addDonation($amount, $source, $description, $userId)
    {
        return DB::transaction(function () use ($amount, $source, $description, $userId) {
            $treasury = Treasury::first();

            if (!$treasury) {
                throw new \Exception('لم يتم العثور على خزينة. يرجى الاتصال بالمسؤول.');
            }

            $treasury->increment('balance', $amount);

            return TreasuryTransaction::create([
                'treasury_id' => $treasury->id,
                'type' => 'donation',
                'source' => $source,
                'amount' => $amount,
                'description' => $description,
                'user_id' => $userId,
                'transaction_date' => now(),
            ]);
        });
    }

    public function recordExpenseWithItems($custodyId, $userId, $amount, $categoryId, $itemId, $description, $location, $socialCaseId = null)
    {
        return DB::transaction(function () use ($custodyId, $userId, $amount, $categoryId, $itemId, $description, $location, $socialCaseId) {
            $custody = Custody::findOrFail($custodyId);

            if ($custody->getRemainingBalance() < $amount) {
                throw new \Exception('الرصيد غير كافي');
            }

            $expense = Expense::create([
                'custody_id' => $custodyId,
                'user_id' => $userId,
                'social_case_id' => $socialCaseId,
                'expense_category_id' => $categoryId,
                'expense_item_id' => $itemId,
                'amount' => $amount,
                'description' => $description,
                'location' => $location,
                'source' => 'custody',
                'expense_date' => now(),
            ]);

            // Update custody spent
            $custody->increment('spent', $amount);

            // Create transaction with category and item tracking
            TreasuryTransaction::create([
                'treasury_id' => $custody->treasury_id,
                'type' => 'expense',
                'amount' => $amount,
                'description' => $description,
                'user_id' => $userId,
                'custody_id' => $custodyId,
                'expense_id' => $expense->id,
                'expense_category_id' => $categoryId,
                'expense_item_id' => $itemId,
                'transaction_date' => now(),
            ]);

            // Get category and item names for notification
            $category = $expense->category;
            $categoryName = $category->name ?? 'غير محدد';
            $itemName = $expense->item ? $expense->item->name : 'غير محدد';
            $message = "مصروف جديد: {$categoryName} - {$itemName} بقيمة {$amount} ج.م";

            // Notify accountants
            $this->notifyAccountants(
                'مصروف جديد',
                $message,
                'info',
                $expense->id,
                'expense'
            );

            // Notify managers
            $this->notifyManagers(
                'مصروف جديد',
                $message,
                'info',
                $expense->id,
                'expense'
            );

            return $expense;
        });
    }

    public function recordDirectExpenseFromTreasury($userId, $amount, $categoryId, $itemId, $description, $location, $socialCaseId = null)
    {
        return DB::transaction(function () use ($userId, $amount, $categoryId, $itemId, $description, $location, $socialCaseId) {
            $treasury = Treasury::first();

            if (!$treasury) {
                throw new \Exception('لم يتم العثور على خزينة. يرجى الاتصال بالمسؤول.');
            }

            if ($treasury->balance < $amount) {
                throw new \Exception(
                    "❌ لا يمكن تنفيذ هذا المصروف من الخزينة\n\n" .
                    $this->insufficientBalanceError($treasury->balance, $amount, 'صرف المصروف من الخزينة')
                );
            }

            // Create expense with treasury source and null custody_id
            $expense = Expense::create([
                'custody_id' => null,
                'user_id' => $userId,
                'social_case_id' => $socialCaseId,
                'expense_category_id' => $categoryId,
                'expense_item_id' => $itemId,
                'amount' => $amount,
                'description' => $description,
                'location' => $location,
                'source' => 'treasury',
                'expense_date' => now(),
            ]);

            // Deduct from treasury
            $treasury->decrement('balance', $amount);

            // Create transaction
            TreasuryTransaction::create([
                'treasury_id' => $treasury->id,
                'type' => 'expense',
                'amount' => $amount,
                'description' => $description,
                'user_id' => $userId,
                'expense_id' => $expense->id,
                'expense_category_id' => $categoryId,
                'expense_item_id' => $itemId,
                'transaction_date' => now(),
            ]);

            // Notify managers about direct treasury spending
            $this->notifyManagers(
                'صرف مباشر من الخزينة',
                "تم صرف {$amount} ج.م مباشرة من الخزينة من قبل {$expense->user->name}",
                'warning',
                $expense->id,
                'expense'
            );

            // Notify accountants about direct treasury spending
            $this->notifyAccountants(
                'صرف مباشر من الخزينة',
                "تم صرف {$amount} ج.م مباشرة من الخزينة من قبل {$expense->user->name}",
                'warning',
                $expense->id,
                'expense'
            );

            return $expense;
        });
    }

    /**
     * Build insufficient balance error message
     */
    private function insufficientBalanceError($availableBalance, $requiredAmount, $operationType = 'العملية')
    {
        $deficit = $requiredAmount - $availableBalance;
        return "{$operationType}: رصيد الخزينة غير كافي\n\n" .
               '💰 الرصيد المتاح: ' . number_format($availableBalance, 2) . ' ج.م' . "\n" .
               '💸 المبلغ المطلوب: ' . number_format($requiredAmount, 2) . ' ج.م' . "\n" .
               '❌ النقص: ' . number_format($deficit, 2) . ' ج.م' . "\n\n" .
               '⚠️ يرجى إضافة أموال للخزينة قبل تنفيذ هذه العملية';
    }

    private function notifyUser($userId, $title, $message, $type, $relatedId, $relatedType)
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
        ]);
    }

    private function notifyManagers($title, $message, $type, $relatedId, $relatedType)
    {
        $managers = User::role('مدير')->get();

        foreach ($managers as $manager) {
            $this->notifyUser($manager->id, $title, $message, $type, $relatedId, $relatedType);
        }
    }

    private function notifyAccountants($title, $message, $type, $relatedId, $relatedType)
    {
        $accountants = User::role('محاسب')->get();

        foreach ($accountants as $accountant) {
            $this->notifyUser($accountant->id, $title, $message, $type, $relatedId, $relatedType);
        }
    }
}
