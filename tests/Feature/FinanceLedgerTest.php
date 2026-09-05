<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceLedgerTest extends TestCase
{
    private function adminWithTwoStudents(): array
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->for($tenant)->create();
        Sanctum::actingAs($admin);

        $a = Student::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ط£ط­ظ…ط¯']);
        $b = Student::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ط¹ظ…ط±']);

        return [$tenant, $admin, $a, $b];
    }

    public function test_charge_and_payment_derive_the_balance(): void
    {
        [$tenant, , $a] = $this->adminWithTwoStudents();

        $this->postJson('/api/v1/admin/finance/transactions', [
            'person_type' => 'student',
            'person_id' => $a->id,
            'transaction_type' => 'charge',
            'amount' => 100,
            'description' => 'ط±ط³ظˆظ… ط§ظ„ظپطµظ„ ط§ظ„ط£ظˆظ„',
        ])->assertCreated();

        $this->postJson('/api/v1/admin/finance/transactions', [
            'person_type' => 'student',
            'person_id' => $a->id,
            'transaction_type' => 'payment',
            'amount' => 60,
            'description' => 'ط¯ظپط¹ط© ظ†ظ‚ط¯ظٹط©',
        ])->assertCreated();

        $response = $this->getJson("/api/v1/admin/finance/people/student/{$a->id}")
            ->assertOk();

        $this->assertSame(40.0, (float) $response->json('data.summary.balance'));
        $this->assertSame(100.0, (float) $response->json('data.summary.charges'));
        $this->assertSame(60.0, (float) $response->json('data.summary.payments'));

        // Both entries are audited.
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'financial_transaction',
            'action' => 'finance.transaction_created',
        ]);
    }

    public function test_transfer_records_both_sides_and_updates_both_balances(): void
    {
        [, , $a, $b] = $this->adminWithTwoStudents();

        foreach ([$a, $b] as $person) {
            $this->postJson('/api/v1/admin/finance/transactions', [
                'person_type' => 'student',
                'person_id' => $person->id,
                'transaction_type' => 'charge',
                'amount' => 100,
            ])->assertCreated();
        }

        $this->postJson('/api/v1/admin/finance/transfers', [
            'from_type' => 'student',
            'from_id' => $a->id,
            'to_type' => 'student',
            'to_id' => $b->id,
            'amount' => 50,
            'description' => 'ط³ط¯ط§ط¯ ظ…ظ† ط£ط­ظ…ط¯ ط¹ظ† ط¹ظ…ط±',
        ])->assertCreated();

        // Two mirror rows share one reference.
        $sender = FinancialTransaction::where('person_id', $a->id)->where('transaction_type', 'transfer')->firstOrFail();
        $receiver = FinancialTransaction::where('person_id', $b->id)->where('transaction_type', 'transfer')->firstOrFail();
        $this->assertSame($sender->reference, $receiver->reference);
        $this->assertSame('money_out', $sender->direction->value);
        $this->assertSame('money_in', $receiver->direction->value);

        $aBalance = $this->getJson("/api/v1/admin/finance/people/student/{$a->id}")->json('data.summary.balance');
        $bBalance = $this->getJson("/api/v1/admin/finance/people/student/{$b->id}")->json('data.summary.balance');

        $this->assertSame(150.0, (float) $aBalance);
        $this->assertSame(50.0, (float) $bBalance);
    }

    public function test_transfer_to_self_is_rejected(): void
    {
        [, , $a] = $this->adminWithTwoStudents();

        $this->postJson('/api/v1/admin/finance/transfers', [
            'from_type' => 'student',
            'from_id' => $a->id,
            'to_type' => 'student',
            'to_id' => $a->id,
            'amount' => 10,
        ])->assertStatus(422);

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_reversal_keeps_history_and_moves_balance_back(): void
    {
        [$tenant, , $a] = $this->adminWithTwoStudents();

        $charge = $this->postJson('/api/v1/admin/finance/transactions', [
            'person_type' => 'student',
            'person_id' => $a->id,
            'transaction_type' => 'charge',
            'amount' => 100,
        ])->json('data.id');

        $this->postJson("/api/v1/admin/finance/transactions/{$charge}/reverse")->assertCreated();

        $original = FinancialTransaction::findOrFail($charge);
        $reversal = FinancialTransaction::where('reverses_id', $charge)->firstOrFail();

        $this->assertNotNull($reversal);
        $this->assertSame('money_in', $reversal->direction->value);
        $this->assertSame($original->amount, $reversal->amount);

        // Balance returns to zero (charge 100 then reversal of 100).
        $this->assertSame(0.0, (float) $this->getJson("/api/v1/admin/finance/people/student/{$a->id}")->json('data.summary.balance'));

        // Original row still exists (never silently deleted) and the reversal is audited.
        $this->assertDatabaseHas('financial_transactions', ['id' => $charge]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'finance.transaction_reversed',
        ]);

        // A reversal cannot itself be reversed.
        $this->postJson("/api/v1/admin/finance/transactions/{$reversal->id}/reverse")
            ->assertStatus(422);
    }

    public function test_reversing_a_transfer_reverses_both_legs(): void
    {
        [, , $a, $b] = $this->adminWithTwoStudents();

        $response = $this->postJson('/api/v1/admin/finance/transfers', [
            'from_type' => 'student',
            'from_id' => $a->id,
            'to_type' => 'student',
            'to_id' => $b->id,
            'amount' => 30,
        ])->assertCreated();

        $sender = FinancialTransaction::find($response->json('data.sender_transaction_id'));

        $this->postJson("/api/v1/admin/finance/transactions/{$sender->id}/reverse")->assertCreated();

        $reversals = FinancialTransaction::whereNotNull('reverses_id')->get();
        $this->assertCount(2, $reversals);

        $this->assertSame(0.0, (float) $this->getJson("/api/v1/admin/finance/people/student/{$a->id}")->json('data.summary.balance'));
        $this->assertSame(0.0, (float) $this->getJson("/api/v1/admin/finance/people/student/{$b->id}")->json('data.summary.balance'));
    }

    public function test_invalid_amounts_and_unknown_persons_are_rejected(): void
    {
        [, , $a] = $this->adminWithTwoStudents();

        $this->postJson('/api/v1/admin/finance/transactions', [
            'person_type' => 'student',
            'person_id' => $a->id,
            'transaction_type' => 'charge',
            'amount' => 0,
        ])->assertStatus(422);

        $this->postJson('/api/v1/admin/finance/transactions', [
            'person_type' => 'student',
            'person_id' => (string) Str::uuid(),
            'transaction_type' => 'charge',
            'amount' => 10,
        ])->assertStatus(422);

        $this->assertDatabaseCount('financial_transactions', 0);
    }
}
