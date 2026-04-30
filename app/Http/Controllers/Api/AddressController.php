<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAddressRequest;
use App\Http\Requests\Api\UpdateAddressRequest;
use App\Http\Resources\Api\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Address::class);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $authUser = $request->user();

        $addresses = Address::query()
            ->when(! $authUser->hasRole('admin'), function ($query) use ($authUser) {
                $query->whereHas('properties.users', function ($relationQuery) use ($authUser) {
                    $relationQuery
                        ->where('users.id', $authUser->id)
                        ->where('property_user.role', 'landlord');
                });
            })
            ->when($validated['city'] ?? null, fn ($query, $city) => $query->where('city', 'like', '%'.$city.'%'))
            ->when($validated['country'] ?? null, fn ($query, $country) => $query->where('country', strtoupper($country)))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return AddressResource::collection($addresses)->response();
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = Address::query()->create($request->validated());

        return response()->json([
            'data' => new AddressResource($address),
        ], Response::HTTP_CREATED);
    }

    public function show(Address $address): JsonResponse
    {
        $this->authorize('view', $address);

        return response()->json([
            'data' => new AddressResource($address),
        ]);
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $address->fill($request->validated());
        $address->save();

        return response()->json([
            'data' => new AddressResource($address),
        ]);
    }

    public function destroy(Address $address): Response
    {
        $this->authorize('delete', $address);

        $address->delete();

        return response()->noContent();
    }
}
