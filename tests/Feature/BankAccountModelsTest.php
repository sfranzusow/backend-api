<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Organization;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_organization_can_own_bank_accounts(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $userBankAccount = BankAccount::factory()->create([
            'user_id' => $user->id,
            'organization_id' => null,
        ]);
        $organizationBankAccount = BankAccount::factory()->create([
            'user_id' => null,
            'organization_id' => $organization->id,
        ]);

        $user->load('bankAccounts');
        $organization->load('bankAccounts');

        $this->assertTrue($user->bankAccounts->first()->is($userBankAccount));
        $this->assertTrue($organization->bankAccounts->first()->is($organizationBankAccount));
    }

    public function test_rental_agreement_can_reference_a_payment_bank_account(): void
    {
        $bankAccount = BankAccount::factory()->create([
            'account_holder' => 'Erika Vermieter',
            'iban' => 'DE89370400440532013000',
        ]);
        $agreement = RentalAgreement::factory()->create([
            'bank_account_id' => $bankAccount->id,
        ]);

        $agreement->load('bankAccount');
        $bankAccount->load('rentalAgreements');

        $this->assertTrue($agreement->bankAccount->is($bankAccount));
        $this->assertTrue($bankAccount->rentalAgreements->first()->is($agreement));
    }

    public function test_default_bank_account_is_unique_per_owner_when_cleared(): void
    {
        $user = User::factory()->create();
        $oldDefault = BankAccount::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
        ]);
        $newDefault = BankAccount::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        $newDefault->clearOtherDefaultsForSameOwner();

        $this->assertFalse($oldDefault->refresh()->is_default);
        $this->assertTrue($newDefault->refresh()->is_default);
    }
}
