<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SocialCase;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The social-cases list: every sort key it declares must sort (found while
 * building saved views): "amount" pointed at a column the table never had,
 * and the list answered an SQL error.
 */
final class SocialCaseListSortTest extends TestCase
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

    private function makeCase(string $name, ?string $amount): SocialCase
    {
        return SocialCase::create([
            'researcher_id' => $this->manager->id,
            'name' => $name,
            'status' => 'pending',
            'requested_amount' => $amount,
        ]);
    }

    public function test_every_sort_key_the_list_declares_sorts_without_an_error(): void
    {
        $this->makeCase('الأولى', '100.00');
        $this->makeCase('الثانية', '300.00');

        $sortable = $this->getJson('/api/v1/social-cases?per_page=1')->assertOk()->json('meta.sortable');
        $this->assertIsArray($sortable);
        $this->assertContains('amount', $sortable);
        $this->assertCount(8, $sortable);

        foreach ($sortable as $key) {
            foreach (['', '-'] as $direction) {
                $this->getJson('/api/v1/social-cases?per_page=1&sort=' . $direction . $key)->assertOk();
            }
        }
    }

    public function test_sorting_by_amount_orders_by_the_requested_amount(): void
    {
        $this->makeCase('الأولى', '100.00');
        $this->makeCase('الثانية', '300.00');

        $descending = $this->getJson('/api/v1/social-cases?sort=-amount')->assertOk()->json('data');
        $this->assertSame(['الثانية', 'الأولى'], array_column($descending, 'name'));

        $ascending = $this->getJson('/api/v1/social-cases?sort=amount')->assertOk()->json('data');
        $this->assertSame(['الأولى', 'الثانية'], array_column($ascending, 'name'));
    }
}
