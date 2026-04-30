<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePropertyRequest;
use App\Http\Requests\Api\UpdatePropertyRequest;
use App\Http\Resources\Api\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Property::class);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['available', 'rented', 'sold'])],
            'type' => ['nullable', Rule::in(['apartment', 'office', 'penthouse', 'studio'])],
            'address_id' => ['nullable', 'integer', Rule::exists('addresses', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;

        $properties = Property::query()
            ->with(['address', 'users:id,name,email'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['address_id'] ?? null, fn ($query, $addressId) => $query->where('address_id', $addressId))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return PropertyResource::collection($properties)->response();
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = Property::query()->create($request->validated());
        $property->load(['address', 'users:id,name,email']);

        return response()->json([
            'data' => new PropertyResource($property),
        ], Response::HTTP_CREATED);
    }

    public function show(Property $property): JsonResponse
    {
        $this->authorize('view', $property);

        $property->loadMissing(['address', 'users:id,name,email']);

        return response()->json([
            'data' => new PropertyResource($property),
        ]);
    }

    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $property->fill($request->validated());
        $property->save();
        $property->load(['address', 'users:id,name,email']);

        return response()->json([
            'data' => new PropertyResource($property),
        ]);
    }

    public function destroy(Property $property): Response
    {
        $this->authorize('delete', $property);

        $property->delete();

        return response()->noContent();
    }
}
