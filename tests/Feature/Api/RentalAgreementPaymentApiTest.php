<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reminder;
use App\Models\RentalAgreement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalAgreementPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_access_rental_agreement_payments(): void
    {
        $agreement = RentalAgreement::factory()->create();
        $payment = Payment::factory()->create([
            'payable_type' => RentalAgreement::class,
            'payable_id' => $agreement->id,
        ]);

        $this->getJson('/api/rental-agreements/'.$agreement->id.'/payments')->assertUnauthorized();
        $this->postJson('/api/rental-agreements/'.$agreement->id.'/payments')->assertUnauthorized();
        $this->getJson('/api/payments/'.$payment->id)->assertUnauthorized();
        $this->patchJson('/api/payments/'.$payment->id)->assertUnauthorized();
        $this->deleteJson('/api/payments/'.$payment->id)->assertUnauthorized();
    }

    public function test_landlord_can_manage_payments_for_own_rental_agreement(): void
    {
        $landlord = User::factory()->create();
        $landlord->assignRole(RoleName::Landlord->value);
        $tenant = User::factory()->create();
        $property = $this->propertyManagedBy($landlord);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/payments', [
                'type' => Payment::TYPE_DEPOSIT,
                'direction' => Payment::DIRECTION_INCOMING,
                'amount' => '2700.00',
                'due_date' => '2026-06-01',
                'description' => 'Kaution',
                'metadata' => [
                    'source' => 'manual',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.payable_type', 'RentalAgreement')
            ->assertJsonPath('data.payable_id', $agreement->id)
            ->assertJsonPath('data.type', Payment::TYPE_DEPOSIT)
            ->assertJsonPath('data.direction', Payment::DIRECTION_INCOMING)
            ->assertJsonPath('data.status', Payment::STATUS_PENDING)
            ->assertJsonPath('data.amount', '2700.00')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.payer_id', $tenant->id)
            ->assertJsonPath('data.payee_id', $landlord->id)
            ->assertJsonPath('data.metadata.source', 'manual');

        $paymentId = $response->json('data.id');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'payable_type' => RentalAgreement::class,
            'payable_id' => $agreement->id,
            'type' => Payment::TYPE_DEPOSIT,
            'direction' => Payment::DIRECTION_INCOMING,
            'status' => Payment::STATUS_PENDING,
            'amount' => '2700.00',
            'payer_id' => $tenant->id,
            'payee_id' => $landlord->id,
        ]);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id.'/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paymentId);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('data.id', $paymentId);

        $this->actingAs($landlord, 'sanctum')
            ->patchJson('/api/payments/'.$paymentId, [
                'status' => Payment::STATUS_PAID,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Payment::STATUS_PAID);

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => Payment::STATUS_PAID,
        ]);
        $this->assertNotNull(Payment::query()->findOrFail($paymentId)->paid_at);

        $this->actingAs($landlord, 'sanctum')
            ->deleteJson('/api/payments/'.$paymentId)
            ->assertNoContent();

        $this->assertDatabaseMissing('payments', [
            'id' => $paymentId,
        ]);
    }

    public function test_outgoing_deposit_refund_defaults_payer_to_landlord_and_payee_to_tenant(): void
    {
        $landlord = User::factory()->create();
        $landlord->assignRole(RoleName::Landlord->value);
        $tenant = User::factory()->create();
        $property = $this->propertyManagedBy($landlord);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/payments', [
                'type' => Payment::TYPE_DEPOSIT_REFUND,
                'direction' => Payment::DIRECTION_OUTGOING,
                'amount' => '2500.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payer_id', $landlord->id)
            ->assertJsonPath('data.payee_id', $tenant->id);
    }

    public function test_tenant_can_view_but_not_manage_own_rental_agreement_payments(): void
    {
        $tenant = User::factory()->create();
        $tenant->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $payment = Payment::factory()->create([
            'payable_type' => RentalAgreement::class,
            'payable_id' => $agreement->id,
            'type' => Payment::TYPE_RENT,
            'direction' => Payment::DIRECTION_INCOMING,
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id.'/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment->id);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/payments/'.$payment->id)
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id);

        $this->actingAs($tenant, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/payments', [
                'type' => Payment::TYPE_RENT,
                'direction' => Payment::DIRECTION_INCOMING,
                'amount' => '1100.00',
            ])
            ->assertForbidden();

        $this->actingAs($tenant, 'sanctum')
            ->patchJson('/api/payments/'.$payment->id, [
                'status' => Payment::STATUS_PAID,
            ])
            ->assertForbidden();

        $this->actingAs($tenant, 'sanctum')
            ->deleteJson('/api/payments/'.$payment->id)
            ->assertForbidden();
    }

    public function test_tenant_can_filter_payments_by_due_date_and_include_assigned_reminders(): void
    {
        $tenant = User::factory()->create();
        $tenant->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $matchingPayment = Payment::factory()->create([
            'payable_type' => RentalAgreement::class,
            'payable_id' => $agreement->id,
            'due_date' => '2026-06-15',
        ]);
        Payment::factory()->create([
            'payable_type' => RentalAgreement::class,
            'payable_id' => $agreement->id,
            'due_date' => '2026-08-01',
        ]);
        $assignedReminder = Reminder::factory()->create([
            'remindable_type' => Payment::class,
            'remindable_id' => $matchingPayment->id,
            'assigned_to_id' => $tenant->id,
            'title' => 'Zahlung pruefen',
        ]);
        $internalReminder = Reminder::factory()->create([
            'remindable_type' => Payment::class,
            'remindable_id' => $matchingPayment->id,
            'title' => 'Intern nachfassen',
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id.'/payments?due_from=2026-06-01&due_until=2026-06-30&include=reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingPayment->id)
            ->assertJsonCount(1, 'data.0.reminders')
            ->assertJsonPath('data.0.reminders.0.id', $assignedReminder->id)
            ->assertJsonMissing(['title' => $internalReminder->title]);
    }

    public function test_payment_index_rejects_unknown_includes(): void
    {
        $tenant = User::factory()->create();
        $tenant->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id.'/payments?include=reminders,unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include']);
    }

    public function test_landlord_cannot_manage_payments_for_unmanaged_rental_agreement(): void
    {
        $landlord = User::factory()->create();
        $landlord->assignRole(RoleName::Landlord->value);
        $agreement = RentalAgreement::factory()->create();
        $payment = Payment::factory()->create([
            'payable_type' => RentalAgreement::class,
            'payable_id' => $agreement->id,
        ]);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id.'/payments')
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/payments', [
                'type' => Payment::TYPE_DEPOSIT,
                'direction' => Payment::DIRECTION_INCOMING,
                'amount' => '2700.00',
            ])
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->patchJson('/api/payments/'.$payment->id, [
                'status' => Payment::STATUS_PAID,
            ])
            ->assertForbidden();
    }

    public function test_payment_request_validates_type_direction_and_amount(): void
    {
        $landlord = User::factory()->create();
        $landlord->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($landlord);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
        ]);

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/payments', [
                'type' => 'unknown',
                'direction' => 'sideways',
                'amount' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'direction', 'amount']);
    }

    private function propertyManagedBy(User $landlord): Property
    {
        $property = Property::factory()->create();
        $property->users()->attach($landlord->id, ['role' => RoleName::Landlord->value]);

        return $property;
    }
}
