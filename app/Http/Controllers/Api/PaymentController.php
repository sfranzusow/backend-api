<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePaymentRequest;
use App\Http\Requests\Api\UpdatePaymentRequest;
use App\Http\Resources\Api\PaymentResource;
use App\Models\Payment;
use App\Models\RentalAgreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        $this->authorize('view', $rentalAgreement);

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(Payment::types())],
            'direction' => ['nullable', 'string', Rule::in(Payment::directions())],
            'status' => ['nullable', 'string', Rule::in(Payment::statuses())],
        ]);

        $payments = $rentalAgreement->payments()
            ->with(['payer:id,name,email', 'payee:id,name,email'])
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['direction'] ?? null, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->get();

        return response()->json([
            'data' => PaymentResource::collection($payments),
        ]);
    }

    public function store(StorePaymentRequest $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        $payment = $rentalAgreement->payments()->create(
            $this->attributesWithRentalAgreementDefaults($request->validated(), $rentalAgreement)
        );

        $payment->load(['payer:id,name,email', 'payee:id,name,email']);

        return response()->json([
            'data' => new PaymentResource($payment),
        ], Response::HTTP_CREATED);
    }

    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->loadMissing(['payer:id,name,email', 'payee:id,name,email']);

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment->forceFill($this->attributesForUpdate($request->validated(), $payment))->save();
        $payment->load(['payer:id,name,email', 'payee:id,name,email']);

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function destroy(Payment $payment): Response
    {
        $this->authorize('delete', $payment);

        $payment->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributesWithRentalAgreementDefaults(array $attributes, RentalAgreement $rentalAgreement): array
    {
        $attributes['status'] ??= Payment::STATUS_PENDING;
        $attributes['currency'] ??= $rentalAgreement->currency ?: 'EUR';

        if ($attributes['direction'] === Payment::DIRECTION_INCOMING) {
            $attributes['payer_id'] ??= $rentalAgreement->tenant_id;
            $attributes['payee_id'] ??= $rentalAgreement->landlord_id;
        } else {
            $attributes['payer_id'] ??= $rentalAgreement->landlord_id;
            $attributes['payee_id'] ??= $rentalAgreement->tenant_id;
        }

        if ($attributes['status'] === Payment::STATUS_PAID) {
            $attributes['paid_at'] ??= now();
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributesForUpdate(array $attributes, Payment $payment): array
    {
        if (($attributes['status'] ?? null) === Payment::STATUS_PAID) {
            $attributes['paid_at'] ??= $payment->paid_at ?? now();
        } elseif (array_key_exists('status', $attributes)) {
            $attributes['paid_at'] ??= null;
        }

        return $attributes;
    }
}
