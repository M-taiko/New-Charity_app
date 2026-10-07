<?php

namespace App\Services;

use App\Models\Custody;
use App\Models\CustodyTransfer;
use App\Models\TreasuryTransaction;
use App\Models\Notification;
use App\Exceptions\TransferBalanceException;
use Illuminate\Support\Facades\DB;

class CustodyTransferService
{
    /**
     * Create a transfer request from one agent to another
     */
    public function createTransferRequest($fromAgentId, $toAgentId, $custodyId, $amount, $notes = null)
    {
        return DB::transaction(function () use ($fromAgentId, $toAgentId, $custodyId, $amount, $notes) {
            // قفل العهدة أولاً حتى لا يسبقنا صرف/طلب آخر على نفس المبلغ (T26)
            $custody = Custody::where('id', $custodyId)->lockForUpdate()->firstOrFail();

            // Verify ownership
            if ($custody->agent_id !== $fromAgentId) {
                throw new \Exception('هذه العهدة لا تخص هذا المندوب');
            }

            // Verify custody is in accepted or active status
            if (!in_array($custody->status, ['accepted', 'active'])) {
                throw new \Exception('العهدة يجب أن تكون في حالة مقبولة أو نشطة');
            }

            // Check remaining balance (يقارن بعد تقريب خانتين عشريتين؛ الرصيد المتاح يستبعد المبالغ المجمدة)
            $amount = round((float) $amount, 2);
            $available = round((float) $custody->getRemainingBalance(), 2);
            if ($available < $amount) {
                throw new \Exception('الرصيد المتاح في العهدة لا يكفي لهذا التحويل. المتاح: ' . number_format($available, 2) . ' ج.م، المطلوب: ' . number_format($amount, 2) . ' ج.م');
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

            // تجميد المبلغ فوراً (مثل حجز بنكي): ينقص الرصيد المتاح حتى البت في التحويل (T26)
            $custody->increment('pending_transfer_out', $amount);

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
     *
     * @param  int|null  $targetCustodyId  العهدة التي يختارها المستقبل لاستلام المبلغ عليها
     *                                      (null = الأقدم تلقائياً — السلوك السابق)
     */
    public function approveTransfer($transfer, $approverId, $targetCustodyId = null)
    {
        try {
            return DB::transaction(function () use ($transfer, $approverId, $targetCustodyId) {
                // Verify receiving agent is approving
                if ($transfer->to_agent_id !== $approverId) {
                    throw new \Exception('فقط المندوب المستقبل يمكنه الموافقة على التحويل');
                }

                // Lock the custody for update to prevent race conditions
                $custody = Custody::where('id', $transfer->custody_id)->lockForUpdate()->first();

                // T26: تحرير تجميد هذا التحويل أولاً (لا ينزل عن صفر: التحويلات القديمة قبل التجميد لا تملك حجزاً)
                $frozen = (float) $custody->pending_transfer_out;
                $release = min((float) $transfer->amount, $frozen);
                if ($release > 0) {
                    $custody->decrement('pending_transfer_out', $release);
                }
                $custody = $custody->fresh();

                // Re-verify balance one more time with fresh data
                // (المبلغ المحرَّر يعود متاحاً لهذا الفحص؛ رسالة صريحة أن المقصود رصيد عهدة المُرسل)
                $available = round((float) $custody->getRemainingBalance(), 2);
                $needed = round((float) $transfer->amount, 2);
                if ($available < $needed) {
                    throw new TransferBalanceException(
                        'رصيد عهدة المُحوِّل لم يعد كافياً لإتمام هذا التحويل. المتاح: '
                        . number_format($available, 2) . ' ج.م، المطلوب: ' . number_format($needed, 2) . ' ج.م'
                    );
                }

                // Update transfer status
                $transfer->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $approverId,
                ]);

                // Deduct from sender's custody using transferred_out (not spent!)
                $custody->increment('transferred_out', $transfer->amount);

                // Close custody if balance reaches zero — وليس بها تحويلات معلقة أخرى (T26)
                if (
                    round((float) $custody->fresh()->getRemainingBalance(), 2) <= 0
                    && (float) $custody->fresh()->pending_transfer_out <= 0
                ) {
                    $custody->update(['status' => 'closed']);
                }

            // Check if receiving agent has an existing active custody for the same treasury
            // (الأقدم أولاً: اختيار حتمي حتى لا يتوزع المبلغ عشوائياً بين عدة عهد نشطة لنفس الخزينة)
            $toAgentCustody = null;
            if ($targetCustodyId) {
                // المستقبل حدد العهدة بنفسه — نتحقق من ملكيتها وتوافق الخزينة وكونها مفتوحة
                $toAgentCustody = Custody::where('id', $targetCustodyId)
                    ->where('agent_id', $transfer->to_agent_id)
                    ->whereIn('status', ['accepted', 'active'])
                    ->first();
                if (!$toAgentCustody) {
                    throw new \Exception('العهدة المختارة للاستلام غير صالحة (يجب أن تكون عهدتك المفتوحة وبنفس خزينة المُرسل)');
                }
                if ((int) $toAgentCustody->treasury_id !== (int) $custody->treasury_id) {
                    throw new \Exception('لا يمكن الاستلام على عهدة بخزينة مختلفة عن خزينة المُحوِّل');
                }
            } else {
                $toAgentCustody = Custody::where('treasury_id', $custody->treasury_id)
                    ->where('agent_id', $transfer->to_agent_id)
                    ->whereIn('status', ['accepted', 'active'])
                    ->orderBy('accepted_at')
                    ->orderBy('id')
                    ->first();
            }

            if ($toAgentCustody) {
                // Lock the receiving custody for update
                $toAgentCustody = Custody::where('id', $toAgentCustody->id)->lockForUpdate()->first();

                // Add to existing custody (increase their transferred_in)
                $toAgentCustody->increment('transferred_in', $transfer->amount);

                // Create incoming transaction for receiver's custody
                TreasuryTransaction::create([
                    'treasury_id' => $custody->treasury_id,
                    'type' => 'custody_transfer_in', // استلام من تحويل
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
                    'transferred_out' => 0,
                    'transferred_in' => 0,
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
                    'type' => 'custody_transfer_in', // استلام من تحويل
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
                'type' => 'custody_transfer_out', // تحويل من العهدة
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
        } catch (TransferBalanceException $e) {
            // بعد فشل المعاملة وتراجعها: إبلاغ المُرسل أن تحويله تعذر وسبب ذلك (T26)
            $this->notifyUser(
                $transfer->from_agent_id,
                'تعذر إتمام التحويل',
                "تعذر إتمام تحويل " . number_format((float) $transfer->amount, 2) . " ج.م من العهدة #{$transfer->custody_id}. {$e->getMessage()}",
                'error',
                $transfer->id,
                'custody_transfer'
            );
            throw $e;
        }
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

            // T26: تحرير تجميد هذا التحويل (لا ينزل عن صفر للتحويلات القديمة غير المجمّدة)
            $custody = Custody::where('id', $transfer->custody_id)->lockForUpdate()->first();
            $release = min((float) $transfer->amount, (float) $custody->pending_transfer_out);
            if ($release > 0) {
                $custody->decrement('pending_transfer_out', $release);
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
