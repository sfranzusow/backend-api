<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrganizationRequest;
use App\Http\Requests\Api\UpdateOrganizationRequest;
use App\Http\Resources\Api\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(['id', 'name', 'type', 'email'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'asc';

        $organizations = Organization::query()
            ->when($validated['name'] ?? null, fn ($query, string $name) => $query->where('name', 'like', '%'.$name.'%'))
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($validated['email'] ?? null, fn ($query, string $email) => $query->where('email', 'like', '%'.$email.'%'))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return OrganizationResource::collection($organizations)->response();
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = Organization::query()->create($request->validated());

        return response()->json([
            'data' => new OrganizationResource($organization),
        ], Response::HTTP_CREATED);
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'data' => new OrganizationResource($organization),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $organization->fill($request->validated());
        $organization->save();

        return response()->json([
            'data' => new OrganizationResource($organization),
        ]);
    }

    public function destroy(Organization $organization): Response
    {
        $this->authorize('delete', $organization);
        $organization->loadCount(['users', 'bankAccounts', 'documentLayoutTemplates']);

        if (
            $organization->users_count > 0
            || $organization->bank_accounts_count > 0
            || $organization->document_layout_templates_count > 0
        ) {
            throw ValidationException::withMessages([
                'organization' => 'Organizations with assigned users, bank accounts or document layouts cannot be deleted.',
            ]);
        }

        $organization->delete();

        return response()->noContent();
    }
}
