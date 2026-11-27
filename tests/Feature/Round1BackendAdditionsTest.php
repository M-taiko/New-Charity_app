<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Custody;
use App\Models\Treasury;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Round-1/2 backend additions the SPA relies on (review request: "include
 * PHPUnit output for these"):
 *   - DashboardController: pending_custodies + custodies_needing_follow_up
 *   - LookupController: attention_counts (sidebar badges)
 *   - HrController: employeeShow (the edit form's missing endpoint)
 *   - CustodyController: the 'active' status count in the list summary
 */
final class Round1BackendAdditionsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole('مدير');
        $this->be($this->manager);
    }

    private function makeCustody(string $status): Custody
    {
        $treasury = Treasury::create(['name' => 'خزينة الاختبار', 'balance' => 100000]);
        $agent = User::factory()->create();

        $custody = Custody::create([
            'treasury_id' => $treasury->id,
            'agent_id' => $agent->id,
            'accountant_id' => $this->manager->id,
            'amount' => 1000,
            'spent' => 0,
            'returned' => 0,
            'status' => $status,
        ]);
        // created_at is not mass-assignable — set it directly (the follow-up
        // window only counts custodies older than 7 days)
        $custody->forceFill(['created_at' => now()->subMonths(2)])->save();

        return $custody;
    }

    public function test_dashboard_returns_pending_and_follow_up_counts(): void
    {
        $this->makeCustody('pending');
        $this->makeCustody('pending');
        // active, money left, no expenses, created 2 months ago → needs follow-up
        $this->makeCustody('active');

        $response = $this->get('/api/v1/dashboard?range=30d');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame(2, $data['pending_custodies']);
        $this->assertGreaterThanOrEqual(1, $data['custodies_needing_follow_up']);
    }

    public function test_lookups_carry_attention_counts_for_managers(): void
    {
        $response = $this->get('/api/v1/lookups');
        $response->assertOk();

        $counts = $response->json('data.attention_counts');
        $this->assertNotNull($counts, 'managers must receive sidebar badge counts');
        $this->assertArrayHasKey('pending_custodies', $counts);
        $this->assertArrayHasKey('pending_edit_requests', $counts);
        $this->assertSame(0, $counts['pending_edit_requests']);
    }

    public function test_lookups_omit_attention_counts_for_agents(): void
    {
        // agents lack view_all_records — the sidebar badges are management-only
        $agent = User::factory()->create();
        $agent->assignRole('مندوب');
        $this->be($agent);

        $response = $this->get('/api/v1/lookups');
        $response->assertOk();
        $this->assertNull($response->json('data.attention_counts'));
    }

    public function test_employee_show_returns_the_edit_form_record(): void
    {
        $employee = \App\Models\Employee::create([
            'user_id' => User::factory()->create()->id,
            'department' => 'تقنية',
            'job_title' => 'مبرمج',
            'hire_date' => '2026-01-01',
            'salary' => 5000,
            'status' => 'active',
        ]);

        $response = $this->get("/api/v1/hr/employees/{$employee->id}");
        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame($employee->id, $data['id']);
        $this->assertSame('تقنية', $data['department']);
        $this->assertSame('2026-01-01', $data['hire_date']);
    }

    public function test_custody_list_summary_includes_active_count(): void
    {
        $response = $this->get('/api/v1/custodies');
        $response->assertOk();
        $this->assertArrayHasKey('active', $response->json('meta.summary'));
    }
}
