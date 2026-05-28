<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRentalAgreementRequest;
use App\Http\Requests\Api\UpdateRentalAgreementRequest;
use App\Http\Resources\Api\RentalAgreementResource;
use App\Models\Document;
use App\Models\RentalAgreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RentalAgreementController extends Controller
{
    /**
     * @var list<string>
     */
    private const BASE_RESPONSE_RELATIONS = [
        'property.address',
        'landlord:id,name,email',
        'tenant:id,name,email',
        'bankAccount',
    ];

    /**
     * @var list<string>
     */
    private const OPTIONAL_RESPONSE_INCLUDES = [
        'documents',
        'payments',
        'reminders',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RentalAgreement::class);

        $validated = $this->validateIndexRequest($request);

        $perPage = $validated['per_page'] ?? 15;
        $authUser = $request->user();

        $agreements = RentalAgreement::query()
            ->with($this->responseRelations($request, $validated))
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
            ->when($validated['starts_from'] ?? null, fn ($query, $date) => $query->where('date_from', '>=', $date))
            ->when($validated['starts_until'] ?? null, fn ($query, $date) => $query->where('date_from', '<=', $date))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return RentalAgreementResource::collection($agreements)->response();
    }

    public function store(StoreRentalAgreementRequest $request): JsonResponse
    {
        $agreement = RentalAgreement::query()->create($request->validated());
        $agreement->load(['property.address', 'landlord:id,name,email', 'tenant:id,name,email', 'bankAccount']);

        return response()->json([
            'data' => new RentalAgreementResource($agreement),
        ], Response::HTTP_CREATED);
    }

    public function show(RentalAgreement $rentalAgreement): JsonResponse
    {
        $this->authorize('view', $rentalAgreement);

        $rentalAgreement->loadMissing(['property.address', 'landlord:id,name,email', 'tenant:id,name,email', 'bankAccount']);

        return response()->json([
            'data' => new RentalAgreementResource($rentalAgreement),
        ]);
    }

    public function update(UpdateRentalAgreementRequest $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        $rentalAgreement->fill($request->validated());
        $rentalAgreement->save();
        $rentalAgreement->load(['property.address', 'landlord:id,name,email', 'tenant:id,name,email', 'bankAccount']);

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

    /**
     * @return array<string, mixed>
     */
    private function validateIndexRequest(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'active', 'terminated', 'ended'])],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')],
            'landlord_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'tenant_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'starts_from' => ['nullable', 'date'],
            'starts_until' => ['nullable', 'date'],
            'include' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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

        if (in_array('documents', $includes, true)) {
            $relations['documents'] = function ($query) use ($limitToTenantView): void {
                $query
                    ->with(['template', 'latestVersion.files', 'creator:id,name,email'])
                    ->when($limitToTenantView, function ($query): void {
                        $query->whereIn('status', Document::tenantVisibleStatuses());
                    })
                    ->latest('id');
            };
        }

        if (in_array('payments', $includes, true)) {
            $relations['payments'] = function ($query): void {
                $query->with(['payable', 'payer:id,name,email', 'payee:id,name,email']);
            };
        }

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
