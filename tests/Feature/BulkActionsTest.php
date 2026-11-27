<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Custody;
use App\Models\Expense;
use App\Models\Notification;
use App\Models\SocialCase;
use App\Models\Treasury;
use App\Models\User;
use App\Services\BulkActionsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N-04 — the bulk actions. Every id is judged on its own: an id the user may
 * not act on is refused and listed, and never blocks the others.
 */
final class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private const REVIEW = '/api/v1/expenses/bulk-review';
    private const ACTIVE = '/api/v1/social-cases/bulk-active';

    private User $manager;
    private Custody $custody;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->manager = User::factory()->create(['name' => 'المدير العام']);
        $this->manager->assignRole('مدير');
    }

    private function user(string $name, string ...$roles): User
    {
        $user = User::factory()->create(['name' => $name]);
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function expense(string $description, string $amount, ?User $by = null): Expense
    {
        $this->custody ??= Custody::create([
            'treasury_id' => Treasury::create(['name' => 'الخزينة الرئيسية', 'balance' => 100000])->id,
            'agent_id' => $this->user('صاحب العهدة', 'مندوب')->id,
            'accountant_id' => $this->manager->id,
            'amount' => 100000,
            'spent' => 0,
            'returned' => 0,
            'status' => 'active',
        ]);

        return Expense::create([
            'custody_id' => $this->custody->id,
            'user_id' => ($by ?? $this->manager)->id,
            'type' => 'general',
            'amount' => $amount,
            'description' => $description,
        ]);
    }

    private function case(User $researcher, bool $active = true): SocialCase
    {
        return SocialCase::create([
            'researcher_id' => $researcher->id,
            'name' => 'حالة ' . uniqid(),
            'phone' => '01000000000',
            'status' => 'approved',
            'is_active' => $active,
        ]);
    }

    /** the id of each refused result, by the code it was refused with */
    private function refusedWith(array $response, string $code): array
    {
        return array_column(array_filter($response['data']['results'], fn (array $line) => ($line['code'] ?? '') === $code), 'id');
    }

    private function done(array $response): array
    {
        return array_column(array_filter($response['data']['results'], fn (array $line) => $line['status'] === 'done'), 'id');
    }

    // ── expenses: review ─────────────────────────────────────────────────────

    public function test_a_refused_id_is_refused_alone_and_the_others_are_reviewed(): void
    {
        $first = $this->expense('أول مصروف', '100.10');
        $second = $this->expense('ثاني مصروف', '2000.20');
        $already = $this->expense('مصروف مراجَع', '50.00');
        $already->update(['reviewed_by' => $this->manager->id, 'reviewed_at' => now()]);
        $missingId = 999999;

        $this->be($this->manager);
        $response = $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => [$first->id, $missingId, $already->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('data.done', 2)
            ->assertJsonPath('data.refused', 2)
            ->assertJsonPath('level', 'warning')
            ->assertJsonPath('meta.action', 'review')
            ->assertJsonPath('meta.requested', 4)
            ->json();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->done($response));
        $this->assertSame([$missingId], $this->refusedWith($response, 'expense.not_found'));
        $this->assertSame([$already->id], $this->refusedWith($response, 'expense.already_reviewed'));
        // every id named has exactly one line, in the order given
        $this->assertSame([$first->id, $missingId, $already->id, $second->id], array_column($response['data']['results'], 'id'));

        // the two were really reviewed, by the manager; the third is as it was
        foreach ([$first, $second] as $expense) {
            $this->assertSame($this->manager->id, $expense->fresh()->reviewed_by);
            $this->assertNotNull($expense->fresh()->reviewed_at);
        }
        $this->assertSame(3, Expense::whereNotNull('reviewed_at')->count());

        // the total is the two that were changed, as a two-decimal string
        $this->assertSame('2100.30', $response['data']['amount']);
        $this->assertStringContainsString('2', $response['message']);
    }

    public function test_each_reviewed_expense_tells_its_agent_once(): void
    {
        $agent = $this->user('المندوب المخطر', 'مندوب');
        $first = $this->expense('مصروف أول', '10.00', $agent);
        $second = $this->expense('مصروف ثان', '20.00', $agent);
        $already = $this->expense('مصروف مراجَع', '30.00', $agent);
        $already->update(['reviewed_by' => $this->manager->id, 'reviewed_at' => now()]);

        $this->be($this->manager);
        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => [$first->id, $second->id, $already->id]])->assertOk();

        // one notification per change: the refused one tells nobody
        $this->assertSame(2, Notification::where('user_id', $agent->id)->count());
    }

    public function test_an_id_named_twice_is_reviewed_once(): void
    {
        $expense = $this->expense('مصروف مكرر', '10.00');

        $this->be($this->manager);
        $response = $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => [$expense->id, $expense->id, $expense->id]])->assertOk()->json();

        $this->assertSame(1, $response['data']['done']);
        $this->assertCount(1, $response['data']['results']);
        $this->assertSame(1, Notification::count());
    }

    public function test_an_accountant_may_review_a_batch(): void
    {
        $expense = $this->expense('مصروف المحاسب', '10.00');
        $accountant = $this->user('المحاسب العام', 'محاسب');

        $this->be($accountant);
        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => [$expense->id]])->assertOk()->assertJsonPath('data.done', 1);
        $this->assertSame($accountant->id, $expense->fresh()->reviewed_by);
    }

    public function test_an_agent_may_not_review_any_batch(): void
    {
        $expense = $this->expense('مصروف المندوب', '10.00');
        $this->be($this->user('مندوب', 'مندوب'));

        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => [$expense->id]])->assertStatus(403)->assertJsonPath('code', 'bulk.forbidden');
        $this->assertNull($expense->fresh()->reviewed_at);
    }

    // ── expenses: unreview ───────────────────────────────────────────────────

    public function test_unreview_refuses_what_is_not_reviewed_and_keeps_going(): void
    {
        $reviewed = $this->expense('مصروف مراجَع', '10.00');
        $unreviewed = $this->expense('مصروف غير مراجَع', '20.00');
        $reviewed->update(['reviewed_by' => $this->manager->id, 'reviewed_at' => now()]);

        $this->be($this->manager);
        $response = $this->postJson(self::REVIEW, ['action' => 'unreview', 'ids' => [$unreviewed->id, $reviewed->id]])
            ->assertOk()
            ->assertJsonPath('meta.action', 'unreview')
            ->json();

        $this->assertSame([$reviewed->id], $this->done($response));
        $this->assertSame([$unreviewed->id], $this->refusedWith($response, 'expense.not_reviewed'));
        $this->assertNull($reviewed->fresh()->reviewed_at);
    }

    public function test_only_a_manager_may_unreview(): void
    {
        $expense = $this->expense('مصروف للإلغاء', '10.00');
        $expense->update(['reviewed_by' => $this->manager->id, 'reviewed_at' => now()]);
        $this->be($this->user('المحاسب العام', 'محاسب'));

        $this->postJson(self::REVIEW, ['action' => 'unreview', 'ids' => [$expense->id]])->assertStatus(403);
        $this->assertNotNull($expense->fresh()->reviewed_at);
    }

    public function test_a_quick_expense_reviewed_in_a_batch_becomes_a_regular_one(): void
    {
        $expense = $this->expense('مصروف سريع', '10.00');
        $expense->update(['is_quick_expense' => true]);

        $this->be($this->manager);
        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => [$expense->id]])->assertOk();

        $this->assertFalse((bool) $expense->fresh()->is_quick_expense);
    }

    // ── social cases: activate / deactivate ──────────────────────────────────

    public function test_an_unauthorised_case_is_refused_alone_and_the_others_are_changed(): void
    {
        $researcher = $this->user('الباحث صاحب الحالات', 'باحث اجتماعي');
        $mineActive = $this->case($researcher, true);
        $mineActiveToo = $this->case($researcher, true);
        $theirs = $this->case($this->user('باحث آخر', 'باحث اجتماعي'), true);
        $missingId = 999999;

        $this->be($researcher);
        $response = $this->postJson(self::ACTIVE, ['active' => false, 'ids' => [$mineActive->id, $theirs->id, $missingId, $mineActiveToo->id]])
            ->assertOk()
            ->assertJsonPath('data.done', 2)
            ->assertJsonPath('data.refused', 2)
            ->json();

        $this->assertEqualsCanonicalizing([$mineActive->id, $mineActiveToo->id], $this->done($response));
        $this->assertSame([$theirs->id], $this->refusedWith($response, 'social_case.not_yours'));
        $this->assertSame([$missingId], $this->refusedWith($response, 'social_case.not_found'));
        $this->assertFalse((bool) $mineActive->fresh()->is_active);
        $this->assertFalse((bool) $mineActiveToo->fresh()->is_active);
        $this->assertTrue((bool) $theirs->fresh()->is_active, "someone else's case is untouched");
    }

    public function test_a_manager_may_change_every_case_and_a_case_already_in_the_state_is_refused(): void
    {
        $active = $this->case($this->user('باحث ما', 'باحث اجتماعي'), true);
        $inactive = $this->case($this->user('باحث آخر', 'باحث اجتماعي'), false);

        $this->be($this->manager);
        $response = $this->postJson(self::ACTIVE, ['active' => false, 'ids' => [$active->id, $inactive->id]])->assertOk()->json();

        $this->assertSame([$active->id], $this->done($response));
        $this->assertSame([$inactive->id], $this->refusedWith($response, 'social_case.already_inactive'));
        $this->assertFalse((bool) $active->fresh()->is_active);
    }

    public function test_a_role_that_does_not_manage_cases_may_not_change_any(): void
    {
        $case = $this->case($this->user('باحث ما', 'باحث اجتماعي'), true);
        $this->be($this->user('مندوب', 'مندوب'));

        $this->postJson(self::ACTIVE, ['active' => false, 'ids' => [$case->id]])->assertStatus(403)->assertJsonPath('code', 'bulk.forbidden');
        $this->assertTrue((bool) $case->fresh()->is_active);
    }

    // ── the request ──────────────────────────────────────────────────────────

    public function test_the_request_is_validated(): void
    {
        $this->be($this->manager);

        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => []])->assertStatus(422);
        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => ['abc']])->assertStatus(422);
        $this->postJson(self::REVIEW, ['action' => 'delete', 'ids' => [1]])->assertStatus(422);
        $this->postJson(self::REVIEW, ['ids' => [1]])->assertStatus(422);
        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => range(1, BulkActionsService::MAX_IDS + 1)])->assertStatus(422);
        $this->postJson(self::ACTIVE, ['active' => true, 'ids' => []])->assertStatus(422);
        $this->postJson(self::ACTIVE, ['ids' => [1]])->assertStatus(422);
    }

    public function test_a_hundred_at_once_is_allowed(): void
    {
        $ids = [];
        for ($index = 0; $index < BulkActionsService::MAX_IDS; $index++) {
            $ids[] = $this->expense('مصروف ' . $index, '1.00')->id;
        }
        $this->be($this->manager);

        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => $ids])->assertOk()->assertJsonPath('data.done', BulkActionsService::MAX_IDS)->assertJsonPath('data.amount', '100.00');
    }

    public function test_the_bulk_actions_need_a_signed_in_user(): void
    {
        $this->postJson(self::REVIEW, ['action' => 'review', 'ids' => [1]])->assertStatus(401);
        $this->postJson(self::ACTIVE, ['active' => true, 'ids' => [1]])->assertStatus(401);
    }
}
