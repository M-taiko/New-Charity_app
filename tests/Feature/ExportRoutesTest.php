<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Treasury;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every list export must answer with a REAL file for both formats.
 *
 * Regression guard: the `…/{id}` show-routes were registered before the
 * `…/export` routes, so "export" bound as the model id and every export on
 * those lists answered 404 — while the lists themselves worked fine.
 */
final class ExportRoutesTest extends TestCase
{
    use RefreshDatabase;

    /** every list the SPA's tables export (listUrl + '/export') */
    private const LISTS = [
        'expenses', 'custodies', 'custodies/movements', 'custodies/return-requests',
        'custody-transfers', 'my-expenses', 'expenses/edit-requests', 'treasury',
        'social-cases', 'my-cases',
        'hr/employees', 'hr/attendance', 'hr/kpi', 'hr/salaries',
        'purchase-requests', 'maintenance-requests', 'suppliers',
        'tasks', 'notifications', 'broadcasts', 'activity-logs', 'users',
        'reports', 'reports/governorates', 'analytics/researchers',
        'reports/social-case-expenses', 'reports/agents-balance',
        'reports/expense-items', 'reports/categories-analytics', 'reports/reconciliation',
    ];

    private const FORMATS = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole('مدير');
        $this->be($manager);

        // the reconciliation report is about treasuries and intentionally
        // answers 404 when none exist — give it one
        Treasury::create(['name' => 'الخزينة الرئيسية', 'balance' => 0]);
    }

    public function test_every_list_exports_a_real_excel_and_pdf_file(): void
    {
        $failures = [];

        foreach (self::LISTS as $list) {
            foreach (self::FORMATS as $format => $contentType) {
                $response = $this->get("/api/v1/{$list}/export?format={$format}");

                $ok = $response->getStatusCode() === 200
                    && str_starts_with((string) $response->headers->get('Content-Type'), $contentType)
                    && $this->hasFileSignature($response, $format);

                if (! $ok) {
                    $body = $this->bodyOf($response);
                    $failures[] = sprintf(
                        '%s [%s] → HTTP %s, %s, %d bytes%s',
                        $list,
                        $format,
                        $response->getStatusCode(),
                        $response->headers->get('Content-Type'),
                        mb_strlen($body),
                        $response->getStatusCode() >= 500 ? ' — ' . mb_substr(strip_tags($body), 0, 160) : '',
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Exports that did not return a real file:\n" . implode("\n", $failures),
        );
    }

    public function test_detail_routes_still_resolve_numeric_ids(): void
    {
        // the whereNumber constraints must not break the show-routes:
        // a non-numeric segment is no longer swallowed as an id
        $this->get('/api/v1/custodies/not-an-id')->assertNotFound();
        $this->get('/api/v1/expenses/export?format=pdf')->assertOk();
    }

    /** xlsx is a ZIP (PK), pdf starts with %PDF */
    private function hasFileSignature(TestResponse $response, string $format): bool
    {
        $body = $this->bodyOf($response);

        return $format === 'xlsx'
            ? str_starts_with($body, 'PK')
            : str_starts_with($body, '%PDF');
    }

    /** works for streamed responses and plain ones alike */
    private function bodyOf(TestResponse $response): string
    {
        $base = $response->baseResponse;

        if ($base instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            ob_start();
            $base->sendContent();
            $content = (string) ob_get_clean();

            return $content;
        }

        return (string) $base->getContent();
    }
}
