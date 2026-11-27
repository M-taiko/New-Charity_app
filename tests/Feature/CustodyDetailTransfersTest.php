<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Custody;
use App\Models\CustodyTransfer;
use App\Models\Treasury;
use App\Models\User;
use App\Services\CustodyTransferService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/custodies/{id} — the custody detail opens for a custody that HAS
 * transfer records. It used to read a relationship that never existed
 * (`decidedBy`): Laravel only looks a relationship up once there are rows to
 * load it for, so a custody without transfers opened — the only kind every
 * test and every matrix visit ever opened — and every custody with a transfer
 * answered 500.
 */
final class CustodyDetailTransfersTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_a_custody_with_transfers_opens_and_names_who_decided_each(): void
    {
        $this->be($this->manager);

        // without a transfer the detail opened — this is why the defect was never seen
        $this->getJson('/api/v1/custodies/' . $this->custody->id)->assertOk()->assertJsonPath('data.transfers', []);

        $approved = app(CustodyTransferService::class)
            ->createTransferRequest($this->agent->id, $this->receiver->id, $this->custody->id, 250);
        $approved->update(['status' => 'approved', 'approved_by' => $this->accountant->id]);

        $rejected = app(CustodyTransferService::class)
            ->createTransferRequest($this->agent->id, $this->receiver->id, $this->custody->id, 100);
        // only the receiving agent may reject a transfer
        app(CustodyTransferService::class)->rejectTransfer($rejected, $this->receiver->id, 'مبلغ خاطئ');

        $pending = app(CustodyTransferService::class)
            ->createTransferRequest($this->agent->id, $this->receiver->id, $this->custody->id, 50);

        $answer = $this->getJson('/api/v1/custodies/' . $this->custody->id);
        $answer->assertOk();

        $transfers = $answer->json('data.transfers');
        $this->assertCount(3, $transfers);
        // by amount: the pending 50, the rejected 100, the approved 250
        $byAmount = collect($transfers)->sortBy('amount')->values()->all();
        $this->assertSame('المحاسب', $byAmount[2]['decided_by'], 'an approved transfer names its approver');
        $this->assertSame('المستقبل', $byAmount[1]['decided_by'], 'a rejected transfer names the one who rejected it');
        $this->assertArrayHasKey('decided_by', $byAmount[0]);
        $this->assertNull($byAmount[0]['decided_by'], 'a pending transfer was decided by nobody');
        $this->assertSame('المندوب', $byAmount[0]['sender']);
        $this->assertSame('المستقبل', $byAmount[0]['receiver']);
        $this->assertSame('مبلغ خاطئ', $byAmount[1]['rejection_reason']);
        $this->assertSame('250.00', $byAmount[2]['amount']);
    }

    public function test_the_agent_of_the_custody_opens_it_with_its_transfers_too(): void
    {
        $transfer = app(CustodyTransferService::class)
            ->createTransferRequest($this->agent->id, $this->receiver->id, $this->custody->id, 250);
        $transfer->update(['status' => 'approved', 'approved_by' => $this->accountant->id]);

        $this->be($this->agent);
        $this->getJson('/api/v1/custodies/' . $this->custody->id)
            ->assertOk()
            ->assertJsonPath('data.transfers.0.decided_by', 'المحاسب');
    }
}
