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
    public function createCustody($treasuryId, $agentId, $accountantId, $amount, $notes = null, $isAgentRequest = false, $isPersonalCustody = false)
    {
        return DB::transaction(function () use ($treasuryId, $agentId, $accountantId, $amount, $notes, $isAgentRequest, $isPersonalCustody) {
            $custody = Custody::create([
                'treasury_id' => $treasuryId,
                'agent_id' => $agentId,
                'accountant_id' => $accountantId,
                'initiated_by' => $isAgentRequest ? 'agent' : 'accountant',
                'amount' => $amount,
                'spent' => 0,
                'returned' => 0,
                'status' => $isPersonalCustody ? 'accepted' : 'pending', // Auto-accept personal custodies
                'notes' => $notes,
                'accepted_at' => $isPersonalCustody ? now() : null,
            ]);

            // Notifications based on request type
            if ($isPersonalCustody) {
                // Personal custody for accountant/manager: auto-accepted, notify managers/other accountants
                $user = User::find($accountantId);
                $userName = $user ? $user->name : 'المستخدم';
                $notifiedUsers = [];

                $this->notifyManagers(
                    'عهدة شخصية جديدة',
                    "{$userName} أنشأ عهدة شخصية بقيمة {$amount} ج.م من خزينة ID {$treasuryId}",
                    'info',
                    $custody->id,
                    'custody',
                    $notifiedUsers
                );
            } elseif ($isAgentRequest) {
                // Agent request: notify managers and accountants for approval
                $agent = User::find($agentId);
                $notifiedUsers = [];

                $this->notifyManagers(
                    'طلب عهدة جديد',
                    "المندوب {$agent->name} يطلب عهدة بقيمة {$amount} ج.م - يرجى المراجعة والموافقة",
                    'warning',
                    $custody->id,
                    'custody',
                    $notifiedUsers
                );
                $this->notifyAccountants(
                    'طلب عهدة جديد',
                    "المندوب {$agent->name} يطلب عهدة بقيمة {$amount} ج.م - يرجى المراجعة والموافقة",
                    'warning',
                    $custody->id,
                    'custody',
                    $notifiedUsers
                );
            } else {
                // Manager/Accountant created: notify agent that custody was assigned
                $accountant = User::find($accountantId);
                $accountantName = $accountant ? $accountant->name : 'المحاسب';
                $this->notifyUser(
                    $agentId,
                    'عهدة جديدة تتطلب موافقتك',
                    "تم إصدار عهدة جديدة بقيمة {$amount} ج.م بواسطة {$accountantName} - يرجى قبول أو رفض العهدة",
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

    /**
     * Accept custody and distribute amount across multiple treasuries
     */
    public function acceptCustodyWithDistribution($custody, $distribution)
    {
        return DB::transaction(function () use ($custody, $distribution) {
            // Ensure this is only for agent-initiated requests
            if ($custody->initiated_by !== 'agent') {
                throw new \Exception('هذه العملية متاحة فقط لطلبات العهد من المندوب');
            }

            // Process distribution and deduct from each treasury
            $firstTreasuryId = null;
            foreach ($distribution as $treasuryId => $data) {
                $treasury = $data['treasury'];
                $amount = $data['amount'];

                if ($firstTreasuryId === null) {
                    $firstTreasuryId = $treasuryId;
                }

                // Lock treasury for update
                $treasuryLocked = Treasury::where('id', $treasury->id)->lockForUpdate()->first();

                // Final check on balance
                if ($treasuryLocked->balance < $amount) {
                    throw new \Exception("رصيد خزينة '{$treasury->name}' أصبح غير كافي");
                }

                // Deduct from treasury
                $treasuryLocked->decrement('balance', $amount);

                // Create transaction record for each withdrawal
                \App\Models\TreasuryTransaction::create([
                    'treasury_id' => $treasury->id,
                    'type' => 'custody_out',
                    'amount' => $amount,
                    'description' => 'عهدة للمندوب ' . $custody->agent->name,
                    'user_id' => auth()->id(),
                    'custody_id' => $custody->id,
                    'transaction_date' => now(),
                ]);
            }

            // Update custody status and link to first treasury (for reference)
            $custody->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'treasury_id' => $firstTreasuryId,  // Link to first treasury for reference
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

    /**
     * Accept custody and deduct from selected treasury
     */
    public function acceptCustodyFromTreasury($custody, $treasury)
    {
        return DB::transaction(function () use ($custody, $treasury) {
            // Ensure this is only for agent-initiated requests
            if ($custody->initiated_by !== 'agent') {
                throw new \Exception('هذه العملية متاحة فقط لطلبات العهد من المندوب');
            }

            // Check if selected treasury has sufficient balance
            if ($treasury->balance < $custody->amount) {
                $treasuryName = $treasury->name ?? 'غير محددة';
                throw new \Exception(
                    "❌ الخزينة '{$treasuryName}' لا تملك رصيد كافي\n\n" .
                    $this->insufficientBalanceError($treasury->balance, $custody->amount, 'الموافقة على العهدة')
                );
            }

            // Update custody status and treasury association
            $custody->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'treasury_id' => $treasury->id,  // Link custody to selected treasury
            ]);

            // Deduct from treasury balance
            $treasury->decrement('balance', $custody->amount);

            // Create treasury transaction
            \App\Models\TreasuryTransaction::create([
                'treasury_id' => $treasury->id,
                'type' => 'custody_out',
                'amount' => $custody->amount,
                'description' => 'عهدة للمندوب ' . $custody->agent->name,
                'user_id' => auth()->id(),
                'custody_id' => $custody->id,
                'transaction_date' => now(),
            ]);

            // Notify agent to acknowledge receipt
            $this->notifyUser(
                $custody->agent_id,
                'تمت الموافقة على عهدتك',
                "تم الموافقة على عهدتك بقيمة {$custody->amount} ج.م من خزينة {$treasury->name}. يرجى تأكيد الاستقبال لصرف الفلوس",
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

            // Lock the custody and treasury for update to prevent race conditions
            $custody = Custody::where('id', $custody->id)->lockForUpdate()->first();
            $treasury = Treasury::where('id', $custody->treasury_id)->lockForUpdate()->first();

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
            // Lock the custody and treasury for update
            $custody = Custody::where('id', $custody->id)->lockForUpdate()->first();
            $treasury = Treasury::where('id', $custody->treasury_id)->lockForUpdate()->first();

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

            // Notify accountants and managers (avoiding duplicates)
            $notifiedUsers = [];
            $this->notifyAccountants(
                'تم قبول العهدة',
                "المندوب {$custody->agent->name} قبل العهدة بقيمة {$custody->amount} ج.م وتم صرف الفلوس",
                'success',
                $custody->id,
                'custody',
                $notifiedUsers
            );
            $this->notifyManagers(
                'تم قبول العهدة',
                "المندوب {$custody->agent->name} قبل العهدة بقيمة {$custody->amount} ج.م وتم صرف الفلوس",
                'success',
                $custody->id,
                'custody',
                $notifiedUsers
            );

            return $custody;
        });
    }

    /**
     * Agent accepts custody created by accountant and receives from selected treasury
     */
    public function agentAcceptCustodyFromTreasury($custody, $treasury)
    {
        return DB::transaction(function () use ($custody, $treasury) {
            // Lock the custody and treasury for update
            $custody = Custody::where('id', $custody->id)->lockForUpdate()->first();
            $treasuryLocked = Treasury::where('id', $treasury->id)->lockForUpdate()->first();

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

            // Check selected treasury balance
            if ($treasuryLocked->balance < $custody->amount) {
                throw new \Exception(
                    $this->insufficientBalanceError($treasuryLocked->balance, $custody->amount, 'قبول العهدة')
                );
            }

            // Deduct from selected treasury immediately
            $treasuryLocked->decrement('balance', $custody->amount);

            // Update custody to link it to the selected treasury
            $custody->update([
                'status' => 'active',
                'accepted_at' => now(),
                'received_at' => now(),
                'treasury_id' => $treasury->id,
            ]);

            // Create transaction record
            TreasuryTransaction::create([
                'treasury_id' => $treasury->id,
                'type' => 'custody_out',
                'amount' => $custody->amount,
                'description' => "صرف عهدة للمندوب {$custody->agent->name} (قبول مباشر من خزينة {$treasury->name})",
                'user_id' => $custody->agent_id,
                'custody_id' => $custody->id,
                'transaction_date' => now(),
            ]);

            // Notify accountants and managers
            $notifiedUsers = [];
            $this->notifyAccountants(
                'تم قبول العهدة',
                "المندوب {$custody->agent->name} قبل العهدة بقيمة {$custody->amount} ج.م من خزينة {$treasury->name} وتم صرف الفلوس",
                'success',
                $custody->id,
                'custody',
                $notifiedUsers
            );
            $this->notifyManagers(
                'تم قبول العهدة',
                "المندوب {$custody->agent->name} قبل العهدة بقيمة {$custody->amount} ج.م من خزينة {$treasury->name} وتم صرف الفلوس",
                'success',
                $custody->id,
                'custody',
                $notifiedUsers
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

            // Notify accountants and managers (avoiding duplicates)
            $notifiedUsers = [];
            $this->notifyAccountants(
                'رفض العهدة من المندوب',
                "المندوب {$custody->agent->name} رفض العهدة بقيمة {$custody->amount} ج.م. السبب: " . ($reason ?? 'غير محدد'),
                'error',
                $custody->id,
                'custody',
                $notifiedUsers
            );
            $this->notifyManagers(
                'رفض العهدة من المندوب',
                "المندوب {$custody->agent->name} رفض العهدة بقيمة {$custody->amount} ج.م. السبب: " . ($reason ?? 'غير محدد'),
                'error',
                $custody->id,
                'custody',
                $notifiedUsers
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

            // Send notification to accountants and managers (avoiding duplicates)
            $notifiedUsers = [];
            $this->notifyAccountants(
                'طلب رد عهدة',
                "المندوب {$custody->agent->name} يطلب إرجاع {$returnedAmount} ج.م من العهدة",
                'warning',
                $custody->id,
                'custody',
                $notifiedUsers
            );

            $this->notifyManagers(
                'طلب رد عهدة',
                "المندوب {$custody->agent->name} يطلب إرجاع {$returnedAmount} ج.م من العهدة",
                'warning',
                $custody->id,
                'custody',
                $notifiedUsers
            );

            return $custody;
        });
    }

    public function approveCustodyReturn($custody)
    {
        return DB::transaction(function () use ($custody) {
            // Lock the custody and treasury for update
            $custody = Custody::where('id', $custody->id)->lockForUpdate()->first();
            $treasury = Treasury::where('id', $custody->treasury_id)->lockForUpdate()->first();

            $returnedAmount = $custody->pending_return;

            // Move from pending to confirmed returned
            $custody->update([
                'returned' => $custody->returned + $returnedAmount,
                'pending_return' => 0,
            ]);

            // Add to treasury
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

            // Don't notify the approver about their own action
            $excludedUsers = [auth()->id()];

            // Notify accountants (excluding the approver)
            $this->notifyAccountants(
                'تم قبول رد العهدة',
                "تم قبول رد المبلغ {$returnedAmount} ج.م من المندوب {$custody->agent->name} وإضافته للخزينة",
                'success',
                $custody->id,
                'custody',
                $excludedUsers
            );

            // Notify managers (excluding the approver)
            $this->notifyManagers(
                'تم قبول رد العهدة',
                "تم قبول رد المبلغ {$returnedAmount} ج.م من المندوب {$custody->agent->name} وإضافته للخزينة",
                'success',
                $custody->id,
                'custody',
                $excludedUsers
            );

            return $custody;
        });
    }

    public function returnCustody($custody, $returnedAmount)
    {
        return DB::transaction(function () use ($custody, $returnedAmount) {
            // Lock the custody and treasury for update
            $custody = Custody::where('id', $custody->id)->lockForUpdate()->first();
            $treasury = Treasury::where('id', $custody->treasury_id)->lockForUpdate()->first();

            $custody->increment('returned', $returnedAmount);

            // Add to treasury
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

                // Create closure transaction (administrative record)
                TreasuryTransaction::create([
                    'treasury_id' => $custody->treasury_id,
                    'type' => 'custody_close',
                    'amount' => 0,
                    'description' => "إقفال عهدة #$custody->id للمندوب {$custody->agent->name} (تم رد المبلغ بالكامل)",
                    'user_id' => auth()->id(),
                    'custody_id' => $custody->id,
                    'transaction_date' => now(),
                ]);
            } elseif ($custody->returned > 0) {
                $custody->update(['status' => 'partially_returned']);
            }

            return $custody;
        });
    }

    /**
     * تبرع خارجي أو استرداد مصروف يُضاف مباشرة لرصيد عهدة المندوب
     */
    public function addExternalDonationToCustody($custody, $amount, $description, $type = 'external_donation')
    {
        return DB::transaction(function () use ($custody, $amount, $description, $type) {
            $custody = Custody::where('id', $custody->id)->lockForUpdate()->first();

            // زيادة مبلغ العهدة مباشرة
            $custody->increment('amount', $amount);

            $typeLabel = $type === 'expense_refund' ? 'استرداد مصروف' : 'تبرع خارجي';

            TreasuryTransaction::create([
                'treasury_id'      => $custody->treasury_id,
                'type'             => $type,
                'amount'           => $amount,
                'description'      => "{$typeLabel} لعهدة المندوب {$custody->agent->name}: {$description}",
                'user_id'          => auth()->id(),
                'custody_id'       => $custody->id,
                'transaction_date' => now(),
            ]);

            // إشعار المندوب
            $this->notifyUser(
                $custody->agent_id,
                "تم إضافة {$typeLabel} لعهدتك",
                "تم إضافة مبلغ " . number_format($amount, 2) . " ج.م لعهدتك. {$description}",
                'success',
                $custody->id,
                'custody'
            );

            return $custody;
        });
    }

    public function addDonation($treasuryId, $amount, $source, $description, $userId)
    {
        return DB::transaction(function () use ($treasuryId, $amount, $source, $description, $userId) {
            $treasury = Treasury::findOrFail($treasuryId);

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

    public function recordExpenseWithItems($custodyId, $userId, $amount, $categoryId, $itemId, $description, $location, $socialCaseId = null, $attachment = null, $type = 'general', $lineItems = null)
    {
        return DB::transaction(function () use ($custodyId, $userId, $amount, $categoryId, $itemId, $description, $location, $socialCaseId, $attachment, $type, $lineItems) {
            // Get all active custodies for the user sorted by oldest first
            $availableCustodies = Custody::where('agent_id', $userId)
                ->whereIn('status', ['accepted', 'active'])
                ->orderBy('created_at', 'asc')
                ->get()
                ->filter(function($custody) {
                    return $custody->getRemainingBalance() > 0;
                });

            // Calculate total available balance
            $totalAvailable = $availableCustodies->sum(function($custody) {
                return $custody->getRemainingBalance();
            });

            if ($totalAvailable < $amount) {
                throw new \Exception('الرصيد المتاح في جميع العهد (' . number_format($totalAvailable, 2) . ' ج.م) غير كافي للمبلغ المطلوب (' . number_format($amount, 2) . ' ج.م)');
            }

            // Create the expense record
            $expense = Expense::create([
                'custody_id' => $custodyId, // Keep for backward compatibility (will be the first custody used)
                'user_id' => $userId,
                'social_case_id' => $socialCaseId,
                'expense_category_id' => $categoryId,
                'expense_item_id' => $itemId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'location' => $location,
                'source' => 'custody',
                'expense_date' => now(),
                'attachment' => $attachment,
                'line_items' => $lineItems,
            ]);

            // Distribute the expense across custodies
            $remainingAmount = $amount;
            $treasuryId = null;

            foreach ($availableCustodies as $custody) {
                if ($remainingAmount <= 0) {
                    break;
                }

                // Lock the custody for update to prevent race conditions
                $custody = Custody::where('id', $custody->id)->lockForUpdate()->first();

                $custodyBalance = $custody->getRemainingBalance();
                $amountFromThisCustody = min($remainingAmount, $custodyBalance);

                // Store treasury ID (all custodies should have the same treasury)
                if (!$treasuryId) {
                    $treasuryId = $custody->treasury_id;
                }

                // Update custody spent
                $custody->increment('spent', $amountFromThisCustody);

                // Create pivot record
                $expense->custodies()->attach($custody->id, ['amount' => $amountFromThisCustody]);

                // Create transaction
                TreasuryTransaction::create([
                    'treasury_id' => $custody->treasury_id,
                    'type' => 'expense',
                    'amount' => $amountFromThisCustody,
                    'description' => $description . ' (من عهدة #' . $custody->id . ')',
                    'user_id' => $userId,
                    'custody_id' => $custody->id,
                    'expense_id' => $expense->id,
                    'expense_category_id' => $categoryId,
                    'expense_item_id' => $itemId,
                    'transaction_date' => now(),
                ]);

                // Close custody if balance reaches zero
                if ($custody->fresh()->getRemainingBalance() <= 0) {
                    $custody->update(['status' => 'closed']);

                    // Create closure transaction (administrative record)
                    TreasuryTransaction::create([
                        'treasury_id' => $custody->treasury_id,
                        'type' => 'custody_close',
                        'amount' => 0,
                        'description' => "إقفال عهدة #$custody->id للمندوب {$custody->agent->name} (رصيد صفر)",
                        'user_id' => $userId,
                        'custody_id' => $custody->id,
                        'transaction_date' => now(),
                    ]);
                }

                $remainingAmount -= $amountFromThisCustody;
            }

            // Get category and item names for notification
            $category = $expense->category;
            $categoryName = $category->name ?? 'غير محدد';
            $itemName = $expense->item ? $expense->item->name : 'غير محدد';
            $message = "مصروف جديد: {$categoryName} - {$itemName} بقيمة {$amount} ج.م";

            // Don't notify the user who created the expense about their own action
            $excludedUsers = [$userId];

            // Notify accountants (excluding the creator)
            $this->notifyAccountants(
                'مصروف جديد',
                $message,
                'info',
                $expense->id,
                'expense',
                $excludedUsers
            );

            // Notify managers (excluding the creator and already notified accountants)
            $this->notifyManagers(
                'مصروف جديد',
                $message,
                'info',
                $expense->id,
                'expense',
                $excludedUsers
            );

            return $expense;
        });
    }

    public function recordDirectExpenseFromTreasury($treasuryId, $userId, $amount, $categoryId, $itemId, $description, $location, $socialCaseId = null, $attachment = null, $type = 'general', $lineItems = null)
    {
        return DB::transaction(function () use ($treasuryId, $userId, $amount, $categoryId, $itemId, $description, $location, $socialCaseId, $attachment, $type, $lineItems) {
            // Lock the treasury for update to prevent race conditions
            $treasury = Treasury::where('id', $treasuryId)->lockForUpdate()->first();

            if (!$treasury) {
                throw new \Exception('لم يتم العثور على خزينة. يرجى الاتصال بالمسؤول.');
            }

            if ($treasury->balance < $amount) {
                throw new \Exception(
                    "❌ لا يمكن تنفيذ هذا المصروف من الخزينة\n\n" .
                    $this->insufficientBalanceError($treasury->balance, $amount, 'صرف المصروف من الخزينة')
                );
            }

            // Create expense with treasury source and treasury_id
            $expense = Expense::create([
                'custody_id' => null,
                'treasury_id' => $treasuryId,
                'user_id' => $userId,
                'social_case_id' => $socialCaseId,
                'expense_category_id' => $categoryId,
                'expense_item_id' => $itemId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'location' => $location,
                'source' => 'treasury',
                'expense_date' => now(),
                'attachment' => $attachment,
                'line_items' => $lineItems,
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

            // Don't notify the user who created the expense about their own action
            $excludedUsers = [$userId];

            // Notify managers about direct treasury spending (excluding the creator)
            $this->notifyManagers(
                'صرف مباشر من الخزينة',
                "تم صرف {$amount} ج.م مباشرة من الخزينة من قبل {$expense->user->name}",
                'warning',
                $expense->id,
                'expense',
                $excludedUsers
            );

            // Notify accountants about direct treasury spending (excluding the creator)
            $this->notifyAccountants(
                'صرف مباشر من الخزينة',
                "تم صرف {$amount} ج.م مباشرة من الخزينة من قبل {$expense->user->name}",
                'warning',
                $expense->id,
                'expense',
                $excludedUsers
            );

            return $expense;
        });
    }

    /**
     * Transfer money between treasuries
     */
    public function transferBetweenTreasuries($fromTreasuryId, $toTreasuryId, $amount, $description, $userId)
    {
        return DB::transaction(function () use ($fromTreasuryId, $toTreasuryId, $amount, $description, $userId) {
            // Validate both treasuries exist
            $fromTreasury = Treasury::findOrFail($fromTreasuryId);
            $toTreasury = Treasury::findOrFail($toTreasuryId);

            if ($fromTreasuryId === $toTreasuryId) {
                throw new \Exception('لا يمكن تحويل الأموال إلى نفس الخزينة');
            }

            if ($fromTreasury->balance < $amount) {
                throw new \Exception(
                    "❌ لا يمكن تنفيذ هذا التحويل\n\n" .
                    $this->insufficientBalanceError($fromTreasury->balance, $amount, 'التحويل بين الخزائن')
                );
            }

            // Perform transfer: deduct from source, add to destination
            $fromTreasury->decrement('balance', $amount);
            $toTreasury->increment('balance', $amount);

            // Create withdrawal transaction from source treasury
            TreasuryTransaction::create([
                'treasury_id' => $fromTreasuryId,
                'type' => 'transfer_out',
                'amount' => $amount,
                'description' => "تحويل إلى خزينة: {$toTreasury->name} - {$description}",
                'user_id' => $userId,
                'transaction_date' => now(),
            ]);

            // Create deposit transaction to destination treasury
            TreasuryTransaction::create([
                'treasury_id' => $toTreasuryId,
                'type' => 'transfer_in',
                'amount' => $amount,
                'description' => "تحويل من خزينة: {$fromTreasury->name} - {$description}",
                'user_id' => $userId,
                'transaction_date' => now(),
            ]);

            // Notify managers
            $excludedUsers = [$userId];
            $this->notifyManagers(
                'تحويل بين الخزائن',
                "تم تحويل {$amount} ج.م من {$fromTreasury->name} إلى {$toTreasury->name}",
                'info',
                $fromTreasuryId,
                'treasury',
                $excludedUsers
            );

            return [
                'from_treasury' => $fromTreasury->fresh(),
                'to_treasury' => $toTreasury->fresh(),
            ];
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

    private function notifyManagers($title, $message, $type, $relatedId, $relatedType, &$excludeUserIds = [])
    {
        $managers = User::role('مدير')->get();

        foreach ($managers as $manager) {
            // Skip if user already notified or excluded
            if (!in_array($manager->id, $excludeUserIds)) {
                $this->notifyUser($manager->id, $title, $message, $type, $relatedId, $relatedType);
                $excludeUserIds[] = $manager->id; // Track notified user
            }
        }
    }

    private function notifyAccountants($title, $message, $type, $relatedId, $relatedType, &$excludeUserIds = [])
    {
        $accountants = User::role('محاسب')->get();

        foreach ($accountants as $accountant) {
            // Skip if user already notified or excluded
            if (!in_array($accountant->id, $excludeUserIds)) {
                $this->notifyUser($accountant->id, $title, $message, $type, $relatedId, $relatedType);
                $excludeUserIds[] = $accountant->id; // Track notified user
            }
        }
    }
}
