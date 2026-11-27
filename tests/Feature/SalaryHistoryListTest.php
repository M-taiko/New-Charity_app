<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryCalculation;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/hr/salary-history — the salary record of every employee: the
 * page the "سجل مرتبات الموظف" tab opens, which had no route at all.
 */
final class SalaryHistoryListTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/hr/salary-history';

    private User $manager;

    private Employee $sami;

    private Employee $nadia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->manager = User::factory()->create(['name' => 'المدير العام']);
        $this->manager->assignRole('مدير');

        $this->sami = $this->employee('سامي خالد');
        $this->nadia = $this->employee('نادية سمير');

        $this->salary($this->sami, 2026, 1, '5000.00', '4800.00', 'paid', '2026-01-31 10:00:00');
        $this->salary($this->sami, 2026, 2, '5000.00', '5000.00', 'approved', '2026-02-28 10:00:00');
        $this->salary($this->nadia, 2026, 2, '7000.00', '7000.00', 'draft', '2026-03-01 09:00:00');
    }

    public function test_the_record_of_every_employee_newest_first_with_names_and_amounts_as_text(): void
    {
        $this->be($this->manager);

        $answer = $this->list();
        $answer->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('meta.filters.0.key', 'employee_id');

        $rows = $answer->json('data');
        $this->assertSame(['نادية سمير', 'سامي خالد', 'سامي خالد'], array_column($rows, 'employee'));
        $this->assertSame('7000.00', $rows[0]['final_salary']);
        $this->assertSame('4800.00', $rows[2]['final_salary']);
        $this->assertSame([2, 2, 1], array_column($rows, 'month'));
        // the table's filter panel and sort arrows are built from the declaration
        $this->assertSame(['employee_id', 'month', 'year', 'status'], array_column($answer->json('meta.filters'), 'key'));
        $this->assertSame(['date', 'year', 'month', 'final_salary'], $answer->json('meta.sortable'));
        $this->assertContains('سامي خالد', array_column($answer->json('meta.filters.0.options'), 'label'));
    }

    public function test_filtered_by_employee_month_year_and_status(): void
    {
        $this->be($this->manager);

        $this->assertEqualsCanonicalizing([1, 2], array_column($this->list(['filter' => ['employee_id' => $this->sami->id]])->json('data'), 'month'));
        $this->assertEqualsCanonicalizing([2, 2], array_column($this->list(['filter' => ['month' => 2]])->json('data'), 'month'));
        $this->assertSame([], $this->list(['filter' => ['year' => 2025]])->json('data'));
        $this->assertSame(['سامي خالد'], array_column($this->list(['filter' => ['status' => 'approved']])->json('data'), 'employee'));
        // the filters combine
        $this->assertSame(['نادية سمير'], array_column($this->list(['filter' => ['month' => 2, 'status' => 'draft']])->json('data'), 'employee'));
    }

    public function test_the_search_box_finds_an_employee_by_name(): void
    {
        $this->be($this->manager);

        $this->assertSame(['نادية سمير'], array_column($this->list(['q' => 'نادية'])->json('data'), 'employee'));
        $this->assertSame([], $this->list(['q' => 'لا يوجد'])->json('data'));
        // a wildcard in what was typed is a character, not a pattern
        $this->assertSame([], $this->list(['q' => '%'])->json('data'));
    }

    public function test_sorted_by_a_declared_key_and_paged(): void
    {
        $this->be($this->manager);

        $this->assertSame(['4800.00', '5000.00', '7000.00'], array_column($this->list(['sort' => 'final_salary'])->json('data'), 'final_salary'));
        $this->assertSame(['7000.00', '5000.00', '4800.00'], array_column($this->list(['sort' => '-final_salary'])->json('data'), 'final_salary'));
        $page = $this->list(['per_page' => 2, 'page' => 2]);
        $page->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);
        $this->assertCount(1, $page->json('data'));
    }

    public function test_what_is_not_declared_is_refused(): void
    {
        $this->be($this->manager);

        $this->list(['filter' => ['department' => 'الإدارة']])->assertStatus(422)->assertJsonPath('code', 'filter.invalid');
        $this->list(['sort' => 'employee'])->assertStatus(422)->assertJsonPath('code', 'sort.invalid');
        // a value that cannot be: a thirteenth month, an employee that does not exist, an unknown status
        $this->list(['filter' => ['month' => 13]])->assertStatus(422)->assertJsonValidationErrors(['filter.month']);
        $this->list(['filter' => ['employee_id' => 999999]])->assertStatus(422)->assertJsonValidationErrors(['filter.employee_id']);
        $this->list(['filter' => ['status' => 'rejected']])->assertStatus(422)->assertJsonValidationErrors(['filter.status']);
    }

    public function test_only_whoever_manages_the_treasury_may_read_it(): void
    {
        $agent = User::factory()->create(['name' => 'مندوب']);
        $agent->assignRole('مندوب');

        $this->get(self::URL)->assertUnauthorized();
        $this->be($agent);
        $this->list()->assertForbidden();
    }

    public function test_the_export_is_a_real_file_of_the_same_rows(): void
    {
        $this->be($this->manager);

        foreach (['xlsx' => 'application/vnd.openxmlformats', 'pdf' => 'application/pdf'] as $format => $contentType) {
            $response = $this->get(self::URL . '/export?format=' . $format . '&filter%5Bstatus%5D=paid');
            $response->assertOk();
            $this->assertStringStartsWith($contentType, (string) $response->headers->get('Content-Type'));
            $body = $this->bodyOf($response);
            $this->assertSame($format === 'xlsx' ? 'PK' : '%PDF', substr($body, 0, $format === 'xlsx' ? 2 : 4));
        }
    }

    private function list(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->get(self::URL . ($query === [] ? '' : '?' . http_build_query($query)));
    }

    /** works for streamed responses and plain ones alike */
    private function bodyOf(\Illuminate\Testing\TestResponse $response): string
    {
        $base = $response->baseResponse;

        if ($base instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            ob_start();
            $base->sendContent();

            return (string) ob_get_clean();
        }

        return (string) $base->getContent();
    }

    private function employee(string $name): Employee
    {
        $user = User::factory()->create(['name' => $name, 'email' => mb_strtolower(str_replace(' ', '.', $name)) . '@example.test']);

        return Employee::create([
            'user_id' => $user->id,
            'department' => 'الإدارة',
            'job_title' => 'موظف',
            'hire_date' => '2025-01-01',
            'salary' => 5000,
            'status' => 'active',
        ]);
    }

    private function salary(Employee $employee, int $year, int $month, string $base, string $final, string $status, string $createdAt): SalaryCalculation
    {
        // `created_at` is not fillable: the record is written unguarded so that "newest first" is a fact of the data
        return SalaryCalculation::unguarded(fn () => SalaryCalculation::create([
            'employee_id' => $employee->id,
            'year' => $year,
            'month' => $month,
            'base_salary' => $base,
            'total_working_days' => 30,
            'attendance_days' => 30,
            'absence_days' => 0,
            'late_days' => 0,
            'leave_days' => 0,
            'calculated_salary' => $base,
            'deductions' => '0.00',
            'bonuses' => '0.00',
            'final_salary' => $final,
            'calculation_method' => 'daily_rate',
            'status' => $status,
            'created_at' => $createdAt,
        ]));
    }
}
