<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRentalAgreementRequest;
use App\Http\Requests\Api\UpdateRentalAgreementRequest;
use App\Http\Resources\Api\RentalAgreementResource;
use App\Models\RentalAgreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class RentalAgreementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RentalAgreement::class);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'active', 'terminated', 'ended'])],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')],
            'landlord_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'tenant_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $authUser = $request->user();

        $agreements = RentalAgreement::query()
            ->with(['property.address', 'landlord:id,name,email', 'tenant:id,name,email'])
            ->when(! $authUser->hasRole('admin'), function ($query) use ($authUser) {
                if ($authUser->hasRole('landlord')) {
                    $query->where('landlord_id', $authUser->id);
                } elseif ($authUser->hasRole('tenant')) {
                    $query->where('tenant_id', $authUser->id);
                }
            })
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['property_id'] ?? null, fn ($query, $propertyId) => $query->where('property_id', $propertyId))
            ->when($validated['landlord_id'] ?? null, fn ($query, $landlordId) => $query->where('landlord_id', $landlordId))
            ->when($validated['tenant_id'] ?? null, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return RentalAgreementResource::collection($agreements)->response();
    }

    public function store(StoreRentalAgreementRequest $request): JsonResponse
    {
        $agreement = RentalAgreement::query()->create($request->validated());
        $agreement->load(['property.address', 'landlord:id,name,email', 'tenant:id,name,email']);

        return response()->json([
            'data' => new RentalAgreementResource($agreement),
        ], Response::HTTP_CREATED);
    }

    public function show(RentalAgreement $rentalAgreement): JsonResponse
    {
        $this->authorize('view', $rentalAgreement);

        $rentalAgreement->loadMissing(['property.address', 'landlord:id,name,email', 'tenant:id,name,email']);

        return response()->json([
            'data' => new RentalAgreementResource($rentalAgreement),
        ]);
    }

    public function update(UpdateRentalAgreementRequest $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        $rentalAgreement->fill($request->validated());
        $rentalAgreement->save();
        $rentalAgreement->load(['property.address', 'landlord:id,name,email', 'tenant:id,name,email']);

        return response()->json([
            'data' => new RentalAgreementResource($rentalAgreement),
        ]);
    }

    public function destroy(RentalAgreement $rentalAgreement): Response
    {
        $this->authorize('delete', $rentalAgreement);

        $rentalAgreement->delete();

        return response()->noContent();
    }
}
