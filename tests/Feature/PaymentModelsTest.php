<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rental_agreement_can_own_generic_payments(): void
    {
        $agreement = RentalAgreement::factory()->create();
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payment = $agreement->payments()->create([
            'type' => Payment::TYPE_DEPOSIT,
            'direction' => Payment::DIRECTION_INCOMING,
            'status' => Payment::STATUS_PENDING,
            'amount' => '2700.00',
            'currency' => 'EUR',
            'due_date' => '2026-06-01',
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'description' => 'Kaution',
            'metadata' => [
                'source' => 'manual',
            ],
        ]);

        $agreement->load('payments');
        $payment->load(['payable', 'payer', 'payee']);

        $this->assertCount(1, $agreement->payments);
        $this->assertTrue($agreement->payments->first()->is($payment));
        $this->assertTrue($payment->payable->is($agreement));
        $this->assertTrue($payment->payer->is($payer));
        $this->assertTrue($payment->payee->is($payee));
        $this->assertSame('2700.00', $payment->amount);
        $this->assertSame(['source' => 'manual'], $payment->metadata);
    }
}
