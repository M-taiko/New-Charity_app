<?php

namespace App\Services;

use App\Models\Custody;
use App\Models\CustodyTransfer;
use App\Models\TreasuryTransaction;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class CustodyTransferService
{
    /**
     * Create a transfer request from one agent to another
     */
    public function createTransferRequest($fromAgentId, $toAgentId, $custodyId, $amount, $notes = null)
    {
        return DB::transaction(function () use ($fromAgentId, $toAgentId, $custodyId, $amount, $notes) {
            $custody = Custody::findOrFail($custodyId);

            // Verify ownership
            if ($custody->agent_id !== $fromAgentId) {
                throw new \Exception('هذه العهدة لا تخص هذا المندوب');
            }

            // Verify custody is in accepted or active status
            if (!in_array($custody->status, ['accepted', 'active'])) {
                throw new \Exception('العهدة يجب أن تكون في حالة مقبولة أو نشطة');
            }

            // Check remaining balance
            if ($custody->getRemainingBalance() < $amount) {
                throw new \Exception('الرصيد المتبقي غير كافي');
            }

            // Create transfer request
            $transfer = CustodyTransfer::create([
                'from_agent_id' => $fromAgentId,
                'to_agent_id' => $toAgentId,
                'custody_id' => $custodyId,
                'amount' => $amount,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            // Collect all users to notify (avoiding duplicates)
            $notifiedUsers = [];

            // Notify receiving agent
            if (!in_array($toAgentId, $notifiedUsers)) {
                $this->notifyUser(
                    $toAgentId,
                    'طلب تحويل عهدة',
                    "المندوب {$custody->agent->name} يطلب تحويل {$amount} ج.م من العهدة",
                    'info',
                    $transfer->id,
                    'custody_transfer'
                );
                $notifiedUsers[] = $toAgentId;
            }

            // Notify accountant
            if (!in_array($custody->accountant_id, $notifiedUsers)) {
                $this->notifyUser(
                    $custody->accountant_id,
                    'طلب تحويل عهدة',
                    "تم طلب تحويل عهدة بقيمة {$amount} ج.م بين المندوبين",
                    'info',
                    $transfer->id,
                    'custody_transfer'
                );
                $notifiedUsers[] = $custody->accountant_id;
            }

            // Notify managers (excluding already notified users)
            $this->notifyManagers(
                'طلب تحويل عهدة',
                "تم طلب تحويل عهدة بقيمة {$amount} ج.م",
                'info',
                $transfer->id,
                'custody_transfer',
                $notifiedUsers
            );

            return $transfer;
        });
    }

    /**
     * Approve the transfer from the receiving agent
     */
    public function approveTransfer($transfer, $approverId)
    {
        return DB::transaction(function () use ($transfer, $approverId) {
            // Verify receiving agent is approving
            if ($transfer->to_agent_id !== $approverId) {
                throw new \Exception('فقط المندوب المستقبل يمكنه الموافقة على التحويل');
            }

            // Re-verify balance one more time
            if ($transfer->custody->getRemainingBalance() < $transfer->amount) {
                throw new \Exception('الرصيد المتبقي غير كافي');
            }

            // Update transfer status
            $transfer->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approverId,
            ]);

            $custody = $transfer->custody;

            // Deduct from sender's custody
            $custody->increment('spent', $transfer->amount);

            // Close custody if balance reaches zero
            if ($custody->fresh()->getRemainingBalance() <= 0) {
                $custody->update(['status' => 'closed']);
            }

            // Check if receiving agent has an existing active custody for the same treasury
            $toAgentCustody = Custody::where('treasury_id', $custody->treasury_id)
                ->where('agent_id', $transfer->to_agent_id)
                ->whereIn('status', ['accepted', 'active'])
                ->first();

            if ($toAgentCustody) {
                // Add to existing custody (increase their available balance)
                $toAgentCustody->increment('amount', $transfer->amount);

                // Create incoming transaction for receiver's custody
                TreasuryTransaction::create([
                    'treasury_id' => $custody->treasury_id,
                    'type' => 'custody_out', // استلام من تحويل
                    'amount' => $transfer->amount,
                    'description' => "تحويل عهدة من المندوب {$transfer->fromAgent->name}",
                    'user_id' => $transfer->to_agent_id,
                    'custody_id' => $toAgentCustody->id,
                    'custody_transfer_id' => $transfer->id,
                    'transaction_date' => now(),
                ]);
            } else {
                // Create new custody for receiving agent
                $newCustody = Custody::create([
                    'treasury_id' => $custody->treasury_id,
                    'agent_id' => $transfer->to_agent_id,
                    'accountant_id' => $custody->accountant_id,
                    'initiated_by' => 'accountant',
                    'amount' => $transfer->amount,
                    'spent' => 0,
                    'returned' => 0,
                    'pending_return' => 0,
                    'status' => 'active',
                    'notes' => "عهدة من تحويل - المندوب المرسل: {$transfer->fromAgent->name}",
                    'accepted_at' => now(),
                    'received_at' => now(),
                ]);

                // Create incoming transaction for new custody
                TreasuryTransaction::create([
                    'treasury_id' => $custody->treasury_id,
                    'type' => 'custody_out', // استلام من تحويل
                    'amount' => $transfer->amount,
                    'description' => "تحويل عهدة من المندوب {$transfer->fromAgent->name}",
                    'user_id' => $transfer->to_agent_id,
                    'custody_id' => $newCustody->id,
                    'custody_transfer_id' => $transfer->id,
                    'transaction_date' => now(),
                ]);
            }

            // Create outgoing transaction for sender's custody
            TreasuryTransaction::create([
                'treasury_id' => $custody->treasury_id,
                'type' => 'expense', // خروج من التحويل
                'amount' => $transfer->amount,
                'description' => "تحويل عهدة إلى المندوب {$transfer->toAgent->name}",
                'user_id' => $transfer->from_agent_id,
                'custody_id' => $custody->id,
                'custody_transfer_id' => $transfer->id,
                'transaction_date' => now(),
            ]);

            // Collect all users to notify (avoiding duplicates)
            // Don't notify the approver about their own action
            $notifiedUsers = [$approverId];

            // Notify sender
            if (!in_array($transfer->from_agent_id, $notifiedUsers)) {
                $this->notifyUser(
                    $transfer->from_agent_id,
                    'تم قبول التحويل',
                    "تم قبول تحويل {$transfer->amount} ج.م إلى المندوب {$transfer->toAgent->name}",
                    'success',
                    $transfer->id,
                    'custody_transfer'
                );
                $notifiedUsers[] = $transfer->from_agent_id;
            }

            // Notify accountant
            if (!in_array($custody->accountant_id, $notifiedUsers)) {
                $this->notifyUser(
                    $custody->accountant_id,
                    'تم قبول التحويل',
                    "تم قبول تحويل عهدة بقيمة {$transfer->amount} ج.م",
                    'success',
                    $transfer->id,
                    'custody_transfer'
                );
                $notifiedUsers[] = $custody->accountant_id;
            }

            // Notify managers (excluding already notified users and approver)
            $this->notifyManagers(
                'تم قبول التحويل',
                "تم قبول تحويل عهدة بقيمة {$transfer->amount} ج.م",
                'success',
                $transfer->id,
                'custody_transfer',
                $notifiedUsers
            );

            return $transfer;
        });
    }

    /**
     * Reject the transfer from the receiving agent
     */
    public function rejectTransfer($transfer, $rejecterId, $rejectionReason = null)
    {
        return DB::transaction(function () use ($transfer, $rejecterId, $rejectionReason) {
            // Verify receiving agent is rejecting
            if ($transfer->to_agent_id !== $rejecterId) {
                throw new \Exception('فقط المندوب المستقبل يمكنه رفض التحويل');
            }

            // Update transfer status
            $transfer->update([
                'status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'approved_at' => now(),
                'approved_by' => $rejecterId,
            ]);

            // Collect all users to notify (avoiding duplicates)
            // Don't notify the rejecter about their own action
            $notifiedUsers = [$rejecterId];

            // Notify sender
            if (!in_array($transfer->from_agent_id, $notifiedUsers)) {
                $this->notifyUser(
                    $transfer->from_agent_id,
                    'تم رفض التحويل',
                    "تم رفض طلب تحويل العهدة. السبب: {$rejectionReason}",
                    'error',
                    $transfer->id,
                    'custody_transfer'
                );
                $notifiedUsers[] = $transfer->from_agent_id;
            }

            // Notify accountant
            if (!in_array($transfer->custody->accountant_id, $notifiedUsers)) {
                $this->notifyUser(
                    $transfer->custody->accountant_id,
                    'تم رفض التحويل',
                    "تم رفض تحويل عهدة بقيمة {$transfer->amount} ج.م",
                    'error',
                    $transfer->id,
                    'custody_transfer'
                );
                $notifiedUsers[] = $transfer->custody->accountant_id;
            }

            // Notify managers (excluding already notified users and rejecter)
            $this->notifyManagers(
                'تم رفض التحويل',
                "تم رفض تحويل عهدة بقيمة {$transfer->amount} ج.م",
                'error',
                $transfer->id,
                'custody_transfer',
                $notifiedUsers
            );

            return $transfer;
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

    private function notifyManagers($title, $message, $type, $relatedId, $relatedType, $excludeUserIds = [])
    {
        $managers = \App\Models\User::role('مدير')->get();

        foreach ($managers as $manager) {
            // Skip if user already notified
            if (!in_array($manager->id, $excludeUserIds)) {
                $this->notifyUser($manager->id, $title, $message, $type, $relatedId, $relatedType);
            }
        }
    }
}
