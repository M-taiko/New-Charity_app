<?php

namespace App\Services;

use App\Models\Treasury;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\TreasuryTransaction;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function createCustody($treasuryId, $agentId, $accountantId, $amount, $notes = null)
    {
        return DB::transaction(function () use ($treasuryId, $agentId, $accountantId, $amount, $notes) {
            $custody = Custody::create([
                'treasury_id' => $treasuryId,
                'agent_id' => $agentId,
                'accountant_id' => $accountantId,
                'amount' => $amount,
                'spent' => 0,
                'returned' => 0,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            // Create notification for agent
            $this->notifyUser(
                $agentId,
                'تم إرسال عهدة',
                "تم إرسال عهدة بقيمة {$amount} في انتظار الموافقة",
                'info',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function acceptCustody($custody)
    {
        return DB::transaction(function () use ($custody) {
            $custody->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            // Deduct from treasury
            $treasury = $custody->treasury;
            $treasury->decrement('balance', $custody->amount);

            // Create transaction record
            TreasuryTransaction::create([
                'treasury_id' => $custody->treasury_id,
                'type' => 'custody_out',
                'amount' => $custody->amount,
                'description' => "صرف عهدة للمندوب {$custody->agent->name}",
                'user_id' => auth()->id(),
                'custody_id' => $custody->id,
                'transaction_date' => now(),
            ]);

            // Notify accountant
            $this->notifyUser(
                $custody->accountant_id,
                'تم قبول العهدة',
                "تم قبول العهدة من قبل المندوب {$custody->agent->name}",
                'info',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function rejectCustody($custody, $reason = null)
    {
        return DB::transaction(function () use ($custody, $reason) {
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
                    "عملية صرف بقيمة {$amount} من العهدة",
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
            $custody->update(['status' => 'pending_return']);

            // Send notification to accountant
            $this->notifyUser(
                $custody->accountant_id,
                'طلب رد عهدة',
                "المندوب {$custody->agent->name} يطلب إرجاع {$returnedAmount} ر.س من العهدة",
                'info',
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
            if ($custody->returned >= $custody->amount) {
                $custody->update(['status' => 'closed']);
            } else {
                $custody->update(['status' => 'accepted']);
            }

            // Notify agent
            $this->notifyUser(
                $custody->agent_id,
                'تم قبول الرد',
                "تم قبول رد المبلغ {$returnedAmount} ر.س من العهدة",
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

            // Notify if large expense
            if ($amount > 1000) {
                $this->notifyManagers(
                    'مصروف كبير',
                    "عملية صرف بقيمة {$amount} من العهدة",
                    'warning',
                    $expense->id,
                    'expense'
                );
            }

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
                throw new \Exception('رصيد الخزينة غير كافي');
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
                "تم صرف {$amount} ر.س مباشرة من الخزينة من قبل {$expense->user->name}",
                'warning',
                $expense->id,
                'expense'
            );

            return $expense;
        });
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
        $managers = \App\Models\User::role('مدير')->get();

        foreach ($managers as $manager) {
            $this->notifyUser($manager->id, $title, $message, $type, $relatedId, $relatedType);
        }
    }
}
