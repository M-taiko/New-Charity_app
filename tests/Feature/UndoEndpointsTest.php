<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Custody;
use App\Models\CustodyTransfer;
use App\Models\Expense;
use App\Models\Notification;
use App\Models\Treasury;
use App\Models\User;
use App\Services\CustodyTransferService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N-06 — the inverse endpoints behind the undo toast. Each one must put the
 * record back exactly as it was, and refuse outside its rules.
 */
final class UndoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private const UNDO_ROUTE = '/api/v1';

    private User $manager;

    private User $accountant;

    private User $agent;

    private User $receiver;

    private Custody $custody;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->manager = User::factory()->create(['name' => 'المدير']);
        $this->manager->assignRole('مدير');
        $this->accountant = User::factory()->create(['name' => 'المحاسب']);
        $this->accountant->assignRole('محاسب');
        $this->agent = User::factory()->create(['name' => 'المندوب']);
        $this->receiver = User::factory()->create(['name' => 'المستقبل']);

        $treasury = Treasury::create(['name' => 'خزينة الاختبار', 'balance' => 100000]);
        $this->custody = Custody::create([
            'treasury_id' => $treasury->id,
            'agent_id' => $this->agent->id,
            'accountant_id' => $this->accountant->id,
            'amount' => 1000,
            'spent' => 0,
            'returned' => 0,
            'status' => 'active',
        ]);
    }

    private function makeExpense(bool $quick = false): Expense
    {
        return Expense::create([
            'custody_id' => $this->custody->id,
            'user_id' => $this->agent->id,
            'type' => 'general',
            'amount' => 100,
            'description' => 'مصروف للاختبار',
            'is_quick_expense' => $quick,
        ]);
    }

    private function makeNotice(User $user, bool $read = false): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'title' => 'إشعار للاختبار',
            'message' => 'نص الإشعار',
            'type' => 'info',
            'is_read' => $read,
            'read_at' => $read ? now()->subHour() : null,
        ]);
    }

    // ── review undo ───────────────────────────────────────────────────────────

    public function test_an_accountant_can_undo_their_own_review_and_the_quick_expense_is_quick_again(): void
    {
        $expense = $this->makeExpense(quick: true);
        $this->be($this->accountant);

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/mark-reviewed")->assertOk();
        $expense->refresh();
        $this->assertNotNull($expense->reviewed_at);
        $this->assertFalse((bool) $expense->is_quick_expense, 'the review converts a quick expense');

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/review-undo")->assertOk();

        $expense->refresh();
        $this->assertNull($expense->reviewed_at, 'back to unreviewed');
        $this->assertNull($expense->reviewed_by);
        $this->assertTrue((bool) $expense->is_quick_expense, 'back to quick');
    }

    public function test_review_undo_puts_back_an_earlier_review_with_its_own_reviewer_and_time(): void
    {
        $expense = $this->makeExpense();
        $firstAt = now()->subDays(3);
        $expense->update(['reviewed_by' => $this->manager->id, 'reviewed_at' => $firstAt]);
        $this->be($this->accountant);

        // a second review over the first — the first is what an undo must restore
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/mark-reviewed")->assertOk();
        $this->assertSame($this->accountant->id, $expense->fresh()->reviewed_by);

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/review-undo")->assertOk();

        $expense->refresh();
        $this->assertSame($this->manager->id, (int) $expense->reviewed_by);
        $this->assertSame($firstAt->format('Y-m-d H:i:s'), $expense->reviewed_at?->format('Y-m-d H:i:s'));
    }

    public function test_review_undo_is_refused_for_someone_else_who_is_not_a_manager(): void
    {
        $expense = $this->makeExpense();
        $this->be($this->accountant);
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/mark-reviewed")->assertOk();

        // another accountant did not make this review, and is not a manager
        $other = User::factory()->create();
        $other->assignRole('محاسب');
        $this->be($other);

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/review-undo")->assertForbidden();
        $this->assertNotNull($expense->fresh()->reviewed_at, 'still reviewed');
    }

    public function test_a_manager_can_undo_a_review_they_did_not_make(): void
    {
        $expense = $this->makeExpense();
        $this->be($this->accountant);
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/mark-reviewed")->assertOk();

        $this->be($this->manager);
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/review-undo")->assertOk();

        $this->assertNull($expense->fresh()->reviewed_at);
    }

    public function test_review_undo_is_refused_once_the_window_has_passed(): void
    {
        $expense = $this->makeExpense();
        $this->be($this->accountant);
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/mark-reviewed")->assertOk();

        $this->travel(11)->minutes();

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/review-undo")
            ->assertStatus(422)
            ->assertJsonPath('code', 'review.undo_expired');
        $this->assertNotNull($expense->fresh()->reviewed_at, 'still reviewed');
    }

    public function test_a_plain_unreview_stays_manager_only_and_keeps_a_quick_expense_regular_then_its_undo_restores_the_review(): void
    {
        $expense = $this->makeExpense(quick: true);

        $this->be($this->accountant);
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/mark-reviewed")->assertOk();

        // the documented rule of unreview (D5-a) is untouched: not for an accountant
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/unreview")->assertForbidden();

        $this->be($this->manager);
        $reviewedAt = $expense->fresh()->reviewed_at->format('Y-m-d H:i:s');
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/unreview")->assertOk();
        $expense->refresh();
        $this->assertNull($expense->reviewed_at);
        $this->assertFalse((bool) $expense->is_quick_expense, 'a plain unreview does not make it quick again');

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/unreview-undo")->assertOk();

        $expense->refresh();
        $this->assertSame($this->accountant->id, (int) $expense->reviewed_by, 'the original reviewer, not the manager who undid it');
        $this->assertSame($reviewedAt, $expense->reviewed_at?->format('Y-m-d H:i:s'));
        $this->assertFalse((bool) $expense->is_quick_expense, 'unchanged by an unreview');
    }

    public function test_unreview_undo_is_refused_after_the_window(): void
    {
        $expense = $this->makeExpense();
        $this->be($this->manager);
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/mark-reviewed")->assertOk();
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/unreview")->assertOk();

        $this->travel(11)->minutes();

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/unreview-undo")
            ->assertStatus(422)
            ->assertJsonPath('code', 'review.undo_expired');
        $this->assertNull($expense->fresh()->reviewed_at);
    }

    public function test_review_undo_needs_the_review_role(): void
    {
        $expense = $this->makeExpense();
        $this->be($this->agent);

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/review-undo")->assertForbidden();
        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/unreview-undo")->assertForbidden();
    }

    // ── transfer restore ──────────────────────────────────────────────────────

    private function makePendingTransfer(int $amount = 250): CustodyTransfer
    {
        return app(CustodyTransferService::class)
            ->createTransferRequest($this->agent->id, $this->receiver->id, $this->custody->id, $amount);
    }

    private function cancelTransfer(CustodyTransfer $transfer, User $by): void
    {
        $this->be($by);
        $this->postJson(self::UNDO_ROUTE . "/custody-transfers/{$transfer->id}/cancel")->assertOk();
        $this->assertSame('cancelled', $transfer->fresh()->status);
    }

    public function test_restore_puts_a_cancelled_transfer_back_to_pending_and_freezes_the_amount_again(): void
    {
        $transfer = $this->makePendingTransfer();
        $this->assertSame(250.0, (float) $this->custody->fresh()->pending_transfer_out);
        $this->cancelTransfer($transfer, $this->agent);
        $this->assertSame(0.0, (float) $this->custody->fresh()->pending_transfer_out);

        $this->postJson(self::UNDO_ROUTE . "/custody-transfers/{$transfer->id}/restore")->assertOk()->assertJsonPath('success', true);

        $transfer->refresh();
        $this->assertSame('pending', $transfer->status);
        $this->assertNull($transfer->cancelled_at);
        $this->assertNull($transfer->cancelled_by);
        $this->assertSame(250.0, (float) $this->custody->fresh()->pending_transfer_out, 'frozen again');
        $this->assertSame(750.0, (float) $this->custody->fresh()->getRemainingBalance());
    }

    public function test_restore_is_refused_for_anyone_but_who_cancelled(): void
    {
        $transfer = $this->makePendingTransfer();
        $this->cancelTransfer($transfer, $this->agent);

        // a manager may cancel, but did not — and the receiver did not either
        $this->be($this->manager);
        $this->postJson(self::UNDO_ROUTE . "/custody-transfers/{$transfer->id}/restore")
            ->assertStatus(422)
            ->assertJsonPath('code', 'restore.not_allowed');
        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame(0.0, (float) $this->custody->fresh()->pending_transfer_out, 'nothing frozen');
    }

    public function test_restore_is_refused_once_the_window_has_passed(): void
    {
        $transfer = $this->makePendingTransfer();
        $this->cancelTransfer($transfer, $this->agent);

        $this->travel(11)->minutes();

        $this->postJson(self::UNDO_ROUTE . "/custody-transfers/{$transfer->id}/restore")
            ->assertStatus(422)
            ->assertJsonPath('code', 'restore.expired');
        $this->assertSame('cancelled', $transfer->fresh()->status);
    }

    public function test_restore_is_refused_when_the_custody_no_longer_holds_the_amount(): void
    {
        $transfer = $this->makePendingTransfer();
        $this->cancelTransfer($transfer, $this->agent);

        // the freed money was spent meanwhile — only 100 is left, the transfer needs 250
        $this->custody->update(['spent' => 900]);

        $response = $this->postJson(self::UNDO_ROUTE . "/custody-transfers/{$transfer->id}/restore");
        $response->assertStatus(422)->assertJsonPath('code', 'transfer.restore_refused');
        $this->assertStringContainsString('الرصيد المتاح', $response->json('message'));
        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame(0.0, (float) $this->custody->fresh()->pending_transfer_out);
    }

    public function test_restore_is_refused_when_the_custody_is_no_longer_open(): void
    {
        $transfer = $this->makePendingTransfer();
        $this->cancelTransfer($transfer, $this->agent);
        $this->custody->update(['status' => 'closed']);

        $this->postJson(self::UNDO_ROUTE . "/custody-transfers/{$transfer->id}/restore")
            ->assertStatus(422)
            ->assertJsonPath('code', 'transfer.restore_refused');
        $this->assertSame('cancelled', $transfer->fresh()->status);
    }

    public function test_restore_of_a_transfer_that_was_not_cancelled_is_a_conflict(): void
    {
        $transfer = $this->makePendingTransfer();
        $this->be($this->agent);

        $this->postJson(self::UNDO_ROUTE . "/custody-transfers/{$transfer->id}/restore")
            ->assertStatus(409)
            ->assertJsonPath('code', 'restore.not_cancelled');
        $this->assertSame('pending', $transfer->fresh()->status);
    }

    // ── notifications ─────────────────────────────────────────────────────────

    public function test_mark_all_read_then_unread_puts_every_notification_back(): void
    {
        $first = $this->makeNotice($this->agent);
        $second = $this->makeNotice($this->agent);
        $this->makeNotice($this->agent, read: true);
        $this->makeNotice($this->receiver);

        $this->be($this->agent);
        $response = $this->postJson(self::UNDO_ROUTE . '/notifications/mark-all-read');
        $response->assertOk();
        $markedAt = $response->json('data.marked_at');
        $this->assertNotNull($markedAt, 'the response carries what an undo needs');
        $this->assertTrue($first->fresh()->is_read);
        $this->assertTrue($second->fresh()->is_read);

        $this->postJson(self::UNDO_ROUTE . '/notifications/unread', ['since' => $markedAt])->assertOk();

        // the two that were unread are unread again; the one read an hour ago stays read
        $this->assertFalse($first->fresh()->is_read);
        $this->assertFalse($second->fresh()->is_read);
        $this->assertTrue(Notification::where('user_id', $this->agent->id)->where('is_read', true)->count() === 1);
        // someone else's notifications are not touched
        $this->assertFalse(Notification::where('user_id', $this->receiver->id)->where('is_read', true)->exists());
    }

    public function test_unread_by_ids_marks_those_back_and_ignores_other_peoples(): void
    {
        $mine = $this->makeNotice($this->agent, read: true);
        $theirs = $this->makeNotice($this->receiver, read: true);

        $this->be($this->agent);
        $this->postJson(self::UNDO_ROUTE . '/notifications/unread', ['ids' => [$mine->id, $theirs->id]])->assertOk();

        $this->assertFalse($mine->fresh()->is_read);
        $this->assertNull($mine->fresh()->read_at);
        $this->assertTrue($theirs->fresh()->is_read, 'only the caller\'s rows move');
    }

    public function test_unread_without_a_selection_is_a_validation_error(): void
    {
        $this->be($this->agent);

        $this->postJson(self::UNDO_ROUTE . '/notifications/unread', [])->assertStatus(422);
        $this->postJson(self::UNDO_ROUTE . '/notifications/unread', ['ids' => []])->assertStatus(422);
    }

    public function test_undo_endpoints_need_a_signed_in_user(): void
    {
        $expense = $this->makeExpense();
        $transfer = $this->makePendingTransfer();

        $this->postJson(self::UNDO_ROUTE . "/expenses/{$expense->id}/review-undo")->assertStatus(401);
        $this->postJson(self::UNDO_ROUTE . "/custody-transfers/{$transfer->id}/restore")->assertStatus(401);
        $this->postJson(self::UNDO_ROUTE . '/notifications/unread', ['ids' => [1]])->assertStatus(401);
    }
}
