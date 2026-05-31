<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\BankAccount;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reminder;
use App\Models\RentalAgreement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalAgreementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authenticated_user_can_create_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $landlord = $user;
        $tenant = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 900,
            'status' => 'draft',
        ])->assertCreated()->assertJsonPath('data.property_id', $property->id);
    }

    public function test_landlord_can_create_rental_agreement_with_own_bank_account(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $tenant = User::factory()->create();
        $bankAccount = BankAccount::factory()->create([
            'user_id' => $user->id,
            'organization_id' => null,
            'account_holder' => 'Erika Vermieter',
            'iban' => 'DE89370400440532013000',
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'tenant_id' => $tenant->id,
            'bank_account_id' => $bankAccount->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 900,
        ])
            ->assertCreated()
            ->assertJsonPath('data.bank_account_id', $bankAccount->id)
            ->assertJsonPath('data.bank_account.account_holder', 'Erika Vermieter')
            ->assertJsonPath('data.bank_account.iban', 'DE89370400440532013000');

        $this->assertDatabaseHas('rental_agreements', [
            'landlord_id' => $user->id,
            'bank_account_id' => $bankAccount->id,
        ]);
    }

    public function test_rental_agreement_contract_detail_fields_are_persisted_and_visible_to_tenant(): void
    {
        $landlord = User::factory()->create();
        $landlord->assignRole(RoleName::Landlord->value);
        $tenant = User::factory()->create();
        $tenant->assignRole(RoleName::Tenant->value);
        $property = $this->propertyManagedBy($landlord);

        $response = $this->actingAs($landlord, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'date_from' => '2026-01-01',
            'date_to' => '2027-12-31',
            'rent_cold' => 900,
            'lease_subject_description' => 'Wohnung mit Einbaukueche.',
            'additional_spaces' => 'Kellerraum 12, Stellplatz 4',
            'shared_facilities' => 'Waschkueche und Fahrradraum',
            'fixed_term_reason' => 'Geplanter Eigenbedarf ab 2028.',
            'handover_due_at' => '2025-12-28',
            'operating_costs_allocation_key' => 'nach Wohnflaeche',
            'renovation_condition' => RentalAgreement::RENOVATION_CONDITION_PARTLY_RENOVATED,
            'renovation_condition_notes' => 'Wohnzimmer frisch gestrichen.',
            'cosmetic_repairs_agreement' => 'Keine starren Fristen.',
            'small_repairs_single_limit' => 120,
            'small_repairs_annual_limit' => 300,
            'handover_protocol_attached' => true,
            'house_rules_attached' => true,
            'operating_costs_overview_attached' => true,
            'energy_certificate_attached' => true,
            'self_disclosure_attached' => false,
            'other_attachments' => 'Fotodokumentation',
            'individual_agreements' => 'Der Garten darf mitbenutzt werden.',
            'notes' => 'Interne Notiz',
        ])
            ->assertCreated()
            ->assertJsonPath('data.additional_spaces', 'Kellerraum 12, Stellplatz 4')
            ->assertJsonPath('data.shared_facilities', 'Waschkueche und Fahrradraum')
            ->assertJsonPath('data.handover_due_at', '2025-12-28')
            ->assertJsonPath('data.renovation_condition', RentalAgreement::RENOVATION_CONDITION_PARTLY_RENOVATED)
            ->assertJsonPath('data.small_repairs_single_limit', '120.00')
            ->assertJsonPath('data.house_rules_attached', true)
            ->assertJsonPath('data.individual_agreements', 'Der Garten darf mitbenutzt werden.')
            ->assertJsonPath('data.notes', 'Interne Notiz');

        $agreementId = $response->json('data.id');

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreementId)
            ->assertOk()
            ->assertJsonPath('data.additional_spaces', 'Kellerraum 12, Stellplatz 4')
            ->assertJsonPath('data.house_rules_attached', true)
            ->assertJsonPath('data.individual_agreements', 'Der Garten darf mitbenutzt werden.')
            ->assertJsonMissingPath('data.notes');
    }

    public function test_rental_agreement_contract_detail_fields_are_validated(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $tenant = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'tenant_id' => $tenant->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 900,
            'renovation_condition' => 'fresh_enough',
            'small_repairs_single_limit' => -1,
            'house_rules_attached' => 'maybe',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'renovation_condition',
                'small_repairs_single_limit',
                'house_rules_attached',
            ]);
    }

    public function test_rental_agreement_rejects_foreign_bank_account(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $tenant = User::factory()->create();
        $foreignBankAccount = BankAccount::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'tenant_id' => $tenant->id,
            'bank_account_id' => $foreignBankAccount->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 900,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_account_id']);
    }

    public function test_landlord_cannot_create_rental_agreement_for_another_landlord(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $otherLandlord = User::factory()->create();
        $otherLandlord->assignRole(RoleName::Landlord->value);
        $tenant = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $otherLandlord->id,
            'tenant_id' => $tenant->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 900,
            'status' => 'draft',
        ])->assertUnprocessable()->assertJsonValidationErrors(['landlord_id']);
    }

    public function test_landlord_cannot_create_rental_agreement_for_unmanaged_property(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create();
        $tenant = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'tenant_id' => $tenant->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 900,
            'status' => 'draft',
        ])->assertUnprocessable()->assertJsonValidationErrors(['property_id']);
    }

    public function test_new_rental_agreements_must_start_as_draft(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $tenant = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'tenant_id' => $tenant->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 900,
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_guest_cannot_access_rental_agreements(): void
    {
        $agreement = RentalAgreement::factory()->create();

        $this->getJson('/api/rental-agreements')->assertUnauthorized();
        $this->getJson('/api/rental-agreements/'.$agreement->id)->assertUnauthorized();
    }

    public function test_landlord_can_filter_own_rental_agreements(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $landlord = $user;
        $tenant = User::factory()->create();

        $matchingAgreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);

        RentalAgreement::factory()->create([
            'status' => 'draft',
            'landlord_id' => User::factory(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements?status=active&property_id='.$property->id.'&landlord_id='.$landlord->id.'&tenant_id='.$tenant->id)
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingAgreement->id);
    }

    public function test_rental_agreement_index_can_filter_start_date_and_include_frontend_relations(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $matchingAgreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'date_from' => '2026-02-01',
        ]);
        RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'date_from' => '2026-05-01',
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $matchingAgreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
        ]);
        $payment = Payment::factory()->create([
            'payable_type' => RentalAgreement::class,
            'payable_id' => $matchingAgreement->id,
        ]);
        $reminder = Reminder::factory()->create([
            'remindable_type' => RentalAgreement::class,
            'remindable_id' => $matchingAgreement->id,
            'title' => 'Vertragsstart pruefen',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements?starts_from=2026-01-01&starts_until=2026-03-31&include=documents,payments,reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingAgreement->id)
            ->assertJsonPath('data.0.documents.0.id', $document->id)
            ->assertJsonPath('data.0.payments.0.id', $payment->id)
            ->assertJsonPath('data.0.reminders.0.id', $reminder->id);
    }

    public function test_tenant_rental_agreement_index_includes_only_visible_documents_and_assigned_reminders(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);
        $visibleDocument = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_SHARED,
            'title' => 'Freigegebenes Dokument',
        ]);
        $hiddenDocument = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
            'title' => 'Internes Dokument',
        ]);
        $assignedReminder = Reminder::factory()->create([
            'remindable_type' => RentalAgreement::class,
            'remindable_id' => $agreement->id,
            'assigned_to_id' => $user->id,
            'title' => 'Bitte Vertrag pruefen',
        ]);
        $internalReminder = Reminder::factory()->create([
            'remindable_type' => RentalAgreement::class,
            'remindable_id' => $agreement->id,
            'title' => 'Interne Wiedervorlage',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements?include=documents,reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.documents')
            ->assertJsonCount(1, 'data.0.reminders')
            ->assertJsonPath('data.0.documents.0.id', $visibleDocument->id)
            ->assertJsonPath('data.0.reminders.0.id', $assignedReminder->id)
            ->assertJsonMissing(['title' => $hiddenDocument->title])
            ->assertJsonMissing(['title' => $internalReminder->title]);
    }

    public function test_rental_agreement_index_rejects_unknown_includes(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements?include=documents,unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include']);
    }

    public function test_it_validates_different_landlord_and_tenant(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $sameUser = $user;

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $sameUser->id,
            'tenant_id' => $sameUser->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 800,
        ])->assertUnprocessable()->assertJsonValidationErrors(['landlord_id']);
    }

    public function test_tenant_can_show_own_rental_agreement_without_address_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
            'notes' => 'Internal handover note',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $agreement->id)
            ->assertJsonPath('data.actions.update', false)
            ->assertJsonPath('data.actions.delete', false)
            ->assertJsonPath('data.actions.create_document', false)
            ->assertJsonPath('data.actions.create_payment', false)
            ->assertJsonMissingPath('data.notes')
            ->assertJsonMissingPath('data.property.address');
    }

    public function test_landlord_rental_agreement_response_exposes_management_actions(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'notes' => 'Signed in office.',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id)
            ->assertSuccessful()
            ->assertJsonPath('data.notes', 'Signed in office.')
            ->assertJsonPath('data.actions.update', true)
            ->assertJsonPath('data.actions.delete', true)
            ->assertJsonPath('data.actions.create_document', true)
            ->assertJsonPath('data.actions.create_payment', true);
    }

    public function test_landlord_can_update_own_rental_agreement_via_put(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/rental-agreements/'.$agreement->id, [
                'status' => 'active',
                'notes' => 'Signed',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.notes', 'Signed');
    }

    public function test_landlord_can_end_active_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/rental-agreements/'.$agreement->id, [
                'status' => 'ended',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'ended');
    }

    public function test_landlord_cannot_revert_active_rental_agreement_to_draft(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/rental-agreements/'.$agreement->id, [
                'status' => 'draft',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_landlord_cannot_update_rental_agreement_to_unmanaged_property(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $unmanagedProperty = Property::factory()->create();
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/rental-agreements/'.$agreement->id, [
                'property_id' => $unmanagedProperty->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['property_id']);
    }

    public function test_landlord_cannot_move_rental_agreement_to_another_landlord(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $otherLandlord = User::factory()->create();
        $otherLandlord->assignRole(RoleName::Landlord->value);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/rental-agreements/'.$agreement->id, [
                'landlord_id' => $otherLandlord->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landlord_id']);
    }

    public function test_landlord_can_delete_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/rental-agreements/'.$agreement->id)
            ->assertNoContent();

        $this->assertNull(RentalAgreement::query()->find($agreement->id));
    }

    public function test_user_role_cannot_access_rental_agreement_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements')
            ->assertForbidden();
    }

    public function test_tenant_only_sees_own_rental_agreements_in_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);

        $ownedAgreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);

        RentalAgreement::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownedAgreement->id);
    }

    public function test_tenant_cannot_update_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/rental-agreements/'.$agreement->id, [
                'notes' => 'Attempt',
            ])
            ->assertForbidden();
    }

    private function propertyManagedBy(User $landlord): Property
    {
        $property = Property::factory()->create();
        $property->users()->attach($landlord->id, ['role' => RoleName::Landlord->value]);

        return $property;
    }
}
