<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\BankAccount;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_access_bank_accounts(): void
    {
        $bankAccount = BankAccount::factory()->create();

        $this->getJson('/api/bank-accounts')->assertUnauthorized();
        $this->postJson('/api/bank-accounts')->assertUnauthorized();
        $this->getJson('/api/bank-accounts/'.$bankAccount->id)->assertUnauthorized();
        $this->patchJson('/api/bank-accounts/'.$bankAccount->id)->assertUnauthorized();
        $this->deleteJson('/api/bank-accounts/'.$bankAccount->id)->assertUnauthorized();
    }

    public function test_landlord_can_manage_own_bank_accounts(): void
    {
        $landlord = $this->userWithRole(RoleName::Landlord);
        $oldDefault = BankAccount::factory()->create([
            'user_id' => $landlord->id,
            'organization_id' => null,
            'is_default' => true,
        ]);

        $response = $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/bank-accounts', [
                'user_id' => $landlord->id,
                'account_holder' => 'Erika Vermieter',
                'iban' => 'de89 3704 0044 0532 0130 00',
                'bic' => 'colsdeddxxx',
                'bank_name' => 'Musterbank',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $landlord->id)
            ->assertJsonPath('data.organization_id', null)
            ->assertJsonPath('data.account_holder', 'Erika Vermieter')
            ->assertJsonPath('data.iban', 'DE89370400440532013000')
            ->assertJsonPath('data.bic', 'COLSDEDDXXX')
            ->assertJsonPath('data.bank_name', 'Musterbank')
            ->assertJsonPath('data.is_default', true);

        $bankAccountId = $response->json('data.id');

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bankAccountId,
            'user_id' => $landlord->id,
            'account_holder' => 'Erika Vermieter',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COLSDEDDXXX',
            'is_default' => true,
        ]);
        $this->assertFalse($oldDefault->refresh()->is_default);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/bank-accounts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $bankAccountId]);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/bank-accounts/'.$bankAccountId)
            ->assertOk()
            ->assertJsonPath('data.id', $bankAccountId);

        $this->actingAs($landlord, 'sanctum')
            ->patchJson('/api/bank-accounts/'.$bankAccountId, [
                'bank_name' => 'Neue Bank',
                'is_default' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.bank_name', 'Neue Bank')
            ->assertJsonPath('data.is_default', false);

        $this->actingAs($landlord, 'sanctum')
            ->deleteJson('/api/bank-accounts/'.$bankAccountId)
            ->assertNoContent();

        $this->assertDatabaseMissing('bank_accounts', [
            'id' => $bankAccountId,
        ]);
    }

    public function test_landlord_can_manage_organization_bank_accounts_for_their_organization(): void
    {
        $organization = Organization::factory()->create();
        $landlord = $this->userWithRole(RoleName::Landlord, [
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/bank-accounts', [
                'organization_id' => $organization->id,
                'account_holder' => 'Musterverwaltung GmbH',
                'iban' => 'DE89370400440532013000',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.organization_id', $organization->id);
    }

    public function test_landlord_cannot_manage_foreign_bank_accounts(): void
    {
        $landlord = $this->userWithRole(RoleName::Landlord);
        $otherLandlord = $this->userWithRole(RoleName::Landlord);
        $foreignBankAccount = BankAccount::factory()->create([
            'user_id' => $otherLandlord->id,
            'organization_id' => null,
        ]);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/bank-accounts/'.$foreignBankAccount->id)
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->patchJson('/api/bank-accounts/'.$foreignBankAccount->id, [
                'bank_name' => 'Nicht erlaubt',
            ])
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/bank-accounts', [
                'user_id' => $otherLandlord->id,
                'account_holder' => 'Fremdes Konto',
                'iban' => 'DE89370400440532013000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'organization_id']);
    }

    public function test_tenant_and_user_roles_cannot_access_bank_accounts(): void
    {
        $tenant = $this->userWithRole(RoleName::Tenant);
        $user = $this->userWithRole(RoleName::User);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/bank-accounts')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/bank-accounts')
            ->assertForbidden();
    }

    public function test_bank_account_request_validates_owner_and_bank_identifiers(): void
    {
        $landlord = $this->userWithRole(RoleName::Landlord);

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/bank-accounts', [
                'user_id' => $landlord->id,
                'organization_id' => Organization::factory()->create()->id,
                'account_holder' => 'Erika Vermieter',
                'iban' => 'DE89370400440532013000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'organization_id']);

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/bank-accounts', [
                'user_id' => $landlord->id,
                'account_holder' => '',
                'iban' => 'invalid',
                'bic' => 'short',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_holder', 'iban', 'bic']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(RoleName $roleName, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($roleName->value);

        return $user;
    }
}
