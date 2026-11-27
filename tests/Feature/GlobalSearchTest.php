<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Custody;
use App\Models\Expense;
use App\Models\SocialCase;
use App\Models\Treasury;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N-01 — GET /api/v1/search: records for the command palette, every group
 * scoped by the rule of the list it stands for. An agent must never see
 * another agent's records through it.
 */
final class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/search';

    private User $manager;

    /** the one every record below is looked up by: "زهير" is in no seeded name */
    private const NEEDLE = 'زهير';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->manager = User::factory()->create(['name' => 'المدير العام']);
        $this->manager->assignRole('مدير');
    }

    private function user(string $name, string $role, ?string $email = null): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email ?? (mb_strtolower(str_replace(' ', '.', $name)) . '@example.test')]);
        $user->assignRole($role);

        return $user;
    }

    private function treasury(): Treasury
    {
        return Treasury::create(['name' => 'الخزينة الرئيسية', 'balance' => 100000]);
    }

    private function custody(User $agent, Treasury $treasury, string $notes = ''): Custody
    {
        return Custody::create([
            'treasury_id' => $treasury->id,
            'agent_id' => $agent->id,
            'accountant_id' => $this->manager->id,
            'amount' => 1000,
            'spent' => 0,
            'returned' => 0,
            'status' => 'active',
            'notes' => $notes,
        ]);
    }

    private function expense(User $user, Custody $custody, string $description): Expense
    {
        return Expense::create([
            'custody_id' => $custody->id,
            'user_id' => $user->id,
            'type' => 'general',
            'amount' => 100,
            'description' => $description,
        ]);
    }

    private function case(User $researcher, string $name, ?string $phone = null): SocialCase
    {
        return SocialCase::create([
            'researcher_id' => $researcher->id,
            'name' => $name,
            'phone' => $phone ?? '01000000000',
            'status' => 'pending',
        ]);
    }

    /** the ids a group answered */
    private function ids(array $response, string $group): array
    {
        return array_column($response['data'][$group] ?? [], 'id');
    }

    private function searchFor(string $query): array
    {
        return $this->getJson(self::URL . '?q=' . urlencode($query))->assertOk()->json();
    }

    // ── what a manager finds ─────────────────────────────────────────────────

    public function test_typing_an_agent_name_finds_their_custodies(): void
    {
        $treasury = $this->treasury();
        $agent = $this->user('زهير المنتج', 'مندوب');
        $other = $this->user('مندوب آخر', 'مندوب');
        $mine = $this->custody($agent, $treasury);
        $this->custody($other, $treasury);

        $this->be($this->manager);
        $response = $this->searchFor('زهير');

        $this->assertSame([$mine->id], $this->ids($response, 'custodies'));
        $hit = $response['data']['custodies'][0];
        $this->assertSame('زهير المنتج', $hit['title']);
        $this->assertSame('الخزينة الرئيسية', $hit['subtitle']);
    }

    /**
     * A one-digit number cannot be searched (two characters at least): these
     * push the next ids to two digits, for the tests that search by number.
     */
    private function twoDigitIdsFromHere(Treasury $treasury): void
    {
        $padding = $this->user('مناسب الحشو', 'مندوب');
        while ((int) Custody::max('id') < 10) {
            $this->custody($padding, $treasury);
        }
    }

    public function test_a_custody_is_found_by_its_number(): void
    {
        $treasury = $this->treasury();
        $agent = $this->user('مندوب رقم', 'مندوب');
        $this->twoDigitIdsFromHere($treasury);
        $custody = $this->custody($agent, $treasury);
        $this->custody($agent, $treasury);

        $this->be($this->manager);
        $response = $this->searchFor((string) $custody->id);

        $this->assertSame([$custody->id], $this->ids($response, 'custodies'));
        // found by its number, shown by its agent
        $this->assertSame('مندوب رقم', $response['data']['custodies'][0]['title']);
    }

    public function test_expenses_are_found_by_the_description_or_by_who_recorded_them(): void
    {
        $treasury = $this->treasury();
        $agent = $this->user('زهير المسجل', 'مندوب');
        $custody = $this->custody($agent, $treasury);
        $byText = $this->expense($this->manager, $custody, 'قرطاسية زهير للمكتب');
        $byUser = $this->expense($agent, $custody, 'بنزين السيارة');
        $this->expense($this->manager, $custody, 'شيء آخر تماماً');

        $this->be($this->manager);
        $response = $this->searchFor('زهير');

        $this->assertEqualsCanonicalizing([$byText->id, $byUser->id], $this->ids($response, 'expenses'));
        $this->assertSame('بنزين السيارة', collect($response['data']['expenses'])->firstWhere('id', $byUser->id)['title']);
        $this->assertSame('زهير المسجل', collect($response['data']['expenses'])->firstWhere('id', $byUser->id)['subtitle']);
    }

    public function test_social_cases_are_found_by_the_name_or_the_phone(): void
    {
        $researcher = $this->user('باحث الاختبار', 'باحث اجتماعي');
        $byName = $this->case($researcher, 'زهير الحلبي', '01222222222');
        $byPhone = $this->case($researcher, 'شخص آخر', '01113333444');
        $this->case($researcher, 'شخص ثالث', '01000000000');

        $this->be($this->manager);
        $this->assertEqualsCanonicalizing([$byName->id], $this->ids($this->searchFor('زهير'), 'social_cases'));
        $this->assertEqualsCanonicalizing([$byPhone->id], $this->ids($this->searchFor('01113333444'), 'social_cases'));
        $this->assertSame('01222222222', $this->searchFor('زهير')['data']['social_cases'][0]['subtitle']);
    }

    public function test_users_are_found_by_the_name_or_the_email(): void
    {
        $this->user('زهير الإداري', 'محاسب', 'zuhair.admin@example.test');

        $this->be($this->manager);
        $this->assertCount(1, $this->searchFor('زهير')['data']['users']);
        $this->assertCount(1, $this->searchFor('zuhair.admin')['data']['users']);
        $this->assertSame('zuhair.admin@example.test', $this->searchFor('زهير')['data']['users'][0]['subtitle']);
    }

    public function test_no_more_than_five_results_per_group(): void
    {
        $treasury = $this->treasury();
        $agent = $this->user('زهير كثير العهد', 'مندوب');
        for ($index = 0; $index < 8; $index++) {
            $this->custody($agent, $treasury);
            $this->expense($agent, $this->custody($agent, $treasury), 'مصروف زهير رقم ' . $index);
        }

        $this->be($this->manager);
        $response = $this->searchFor('زهير');

        $this->assertCount(5, $response['data']['custodies']);
        $this->assertCount(5, $response['data']['expenses']);
        $this->assertSame(5, $response['meta']['per_group']);
    }

    public function test_the_answer_names_what_was_searched(): void
    {
        $this->be($this->manager);
        $response = $this->searchFor('لا شيء بهذا الاسم');

        $this->assertSame(['custodies', 'expenses', 'social_cases', 'users'], $response['meta']['groups']);
        $this->assertSame('لا شيء بهذا الاسم', $response['meta']['q']);
        foreach (['custodies', 'expenses', 'social_cases', 'users'] as $group) {
            $this->assertSame([], $response['data'][$group]);
        }
    }

    // ── the visibility rules ─────────────────────────────────────────────────

    public function test_an_agent_sees_their_own_records_and_never_another_agents(): void
    {
        $treasury = $this->treasury();
        $agent = $this->user('زهير المندوب', 'مندوب');
        $other = $this->user('زهير الآخر', 'مندوب');
        $this->twoDigitIdsFromHere($treasury);
        $mine = $this->custody($agent, $treasury);
        $theirs = $this->custody($other, $treasury);
        $myExpense = $this->expense($agent, $mine, 'مصروف زهير الخاص');
        $theirExpense = $this->expense($other, $theirs, 'مصروف زهير الآخر');

        $this->be($agent);
        $response = $this->searchFor('زهير');

        // their own custodies — by the agent's name or by the number — and not one of anyone else's
        $this->assertSame([$mine->id], $this->ids($response, 'custodies'));
        $this->assertSame([$mine->id], $this->ids($this->searchFor((string) $mine->id), 'custodies'));
        $this->assertNotContains($theirs->id, $this->ids($this->searchFor((string) $theirs->id), 'custodies'));
        $this->assertNotContains($theirs->id, $this->ids($this->searchFor('زهير'), 'custodies'));
        // their own expenses only: the other agent's, recorded by him and naming the needle, never shows
        $this->assertSame([$myExpense->id], $this->ids($response, 'expenses'));
        $this->assertNotContains($theirExpense->id, $this->ids($this->searchFor('زهير الآخر'), 'expenses'));
        // and nothing of the groups an agent has no right to
        $this->assertSame([], $response['data']['social_cases']);
        $this->assertSame([], $response['data']['users']);
        $this->assertSame(['custodies', 'expenses'], $response['meta']['groups']);
    }

    public function test_a_researcher_finds_their_own_cases_only(): void
    {
        $researcher = $this->user('باحث زهير', 'باحث اجتماعي');
        $colleague = $this->user('باحث آخر', 'باحث اجتماعي');
        $mine = $this->case($researcher, 'زهير الأول');
        $theirs = $this->case($colleague, 'زهير الثاني');

        $this->be($researcher);
        $response = $this->searchFor('زهير');

        $this->assertSame([$mine->id], $this->ids($response, 'social_cases'));
        $this->assertNotContains($theirs->id, $this->ids($response, 'social_cases'));
        $this->assertSame(['expenses', 'social_cases'], $response['meta']['groups']);
    }

    public function test_an_accountant_sees_all_cases(): void
    {
        $researcher = $this->user('باحث الاختبار', 'باحث اجتماعي');
        $this->case($researcher, 'زهير الأول');
        $this->case($researcher, 'زهير الثاني');

        $this->be($this->user('محاسب زهير', 'محاسب'));
        $this->assertCount(2, $this->searchFor('زهير')['data']['social_cases']);
    }

    public function test_a_role_without_a_right_gets_empty_groups_not_a_refusal(): void
    {
        $treasury = $this->treasury();
        $agent = $this->user('زهير المندوب', 'مندوب');
        $this->custody($agent, $treasury);
        $researcher = $this->user('باحث الاختبار', 'باحث اجتماعي');
        $this->case($researcher, 'زهير الحالة');
        $this->user('زهير المستخدم', 'محاسب');

        $researcher->assignRole('مندوب');
        $this->be($researcher);

        $response = $this->getJson(self::URL . '?q=' . urlencode('زهير'))->assertOk()->assertJsonStructure(['data' => ['custodies', 'expenses', 'social_cases', 'users'], 'meta' => ['q', 'per_group', 'groups']]);

        $this->assertSame([], $response->json('data.users'));
        $this->assertSame([], $response->json('data.custodies'));
        $this->assertNotContains('users', $response->json('meta.groups'));
    }

    // ── the request ──────────────────────────────────────────────────────────

    public function test_two_characters_at_least(): void
    {
        $this->be($this->manager);

        $this->getJson(self::URL . '?q=' . urlencode('ز'))->assertStatus(422)->assertJsonValidationErrors(['q']);
        $this->getJson(self::URL . '?q=' . urlencode('  '))->assertStatus(422);
        $this->getJson(self::URL)->assertStatus(422);
        $this->getJson(self::URL . '?q=' . urlencode('زهير'))->assertOk();
    }

    public function test_the_search_text_cannot_bring_its_own_wildcards(): void
    {
        $treasury = $this->treasury();
        $agent = $this->user('مندوب عادي', 'مندوب');
        $this->custody($agent, $treasury);

        $this->be($this->manager);

        // "_" and "%" are literal here: unescaped, both of these would match every agent's name
        // (a single character cannot be searched at all — two characters at least)
        $this->assertSame([], $this->searchFor('__')['data']['custodies']);
        $this->assertSame([], $this->searchFor('_%')['data']['custodies']);
    }

    public function test_the_search_needs_a_signed_in_user(): void
    {
        $this->getJson(self::URL . '?q=' . urlencode('زهير'))->assertStatus(401);
    }
}
