<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
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
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * @var list<string>
     */
    private const BASE_RESPONSE_RELATIONS = [
        'payable',
        'payer:id,name,email',
        'payee:id,name,email',
    ];

    /**
     * @var list<string>
     */
    private const OPTIONAL_RESPONSE_INCLUDES = [
        'reminders',
    ];

    public function index(Request $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        $this->authorize('view', $rentalAgreement);

        $validated = $this->validateIndexRequest($request);

        $payments = $rentalAgreement->payments()
            ->with($this->responseRelations($request, $validated))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['direction'] ?? null, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['due_from'] ?? null, fn ($query, $date) => $query->where('due_date', '>=', $date))
            ->when($validated['due_until'] ?? null, fn ($query, $date) => $query->where('due_date', '<=', $date))
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

        $payment->setRelation('payable', $rentalAgreement);
        $payment->load(['payer:id,name,email', 'payee:id,name,email']);

        return response()->json([
            'data' => new PaymentResource($payment),
        ], Response::HTTP_CREATED);
    }

    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->loadMissing(['payable', 'payer:id,name,email', 'payee:id,name,email']);

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment->forceFill($this->attributesForUpdate($request->validated(), $payment))->save();
        $payment->load(['payable', 'payer:id,name,email', 'payee:id,name,email']);

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

    /**
     * @return array<string, mixed>
     */
    private function validateIndexRequest(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(Payment::types())],
            'direction' => ['nullable', 'string', Rule::in(Payment::directions())],
            'status' => ['nullable', 'string', Rule::in(Payment::statuses())],
            'due_from' => ['nullable', 'date'],
            'due_until' => ['nullable', 'date'],
            'include' => ['nullable', 'string', 'max:255'],
        ]);

        $this->requestedIncludes($validated);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int|string, mixed>
     */
    private function responseRelations(Request $request, array $validated): array
    {
        $relations = self::BASE_RESPONSE_RELATIONS;
        $includes = $this->requestedIncludes($validated);
        $authUser = $request->user();
        $limitToTenantView = $authUser?->hasRole(RoleName::Tenant->value) === true
            && ! $authUser->hasRole(RoleName::Landlord->value);

        if (in_array('reminders', $includes, true)) {
            $relations['reminders'] = function ($query) use ($authUser, $limitToTenantView): void {
                $query
                    ->with(['remindable', 'creator:id,name,email', 'assignee:id,name,email'])
                    ->when($limitToTenantView, function ($query) use ($authUser): void {
                        $query->where('assigned_to_id', $authUser->id);
                    });
            };
        }

        return $relations;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function requestedIncludes(array $validated): array
    {
        $include = $validated['include'] ?? '';

        if (! is_string($include) || $include === '') {
            return [];
        }

        $includes = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $include)),
            fn (string $value): bool => $value !== '',
        )));

        $invalidIncludes = array_diff($includes, self::OPTIONAL_RESPONSE_INCLUDES);

        if ($invalidIncludes !== []) {
            throw ValidationException::withMessages([
                'include' => 'The selected include is invalid.',
            ]);
        }

        return $includes;
    }
}
