<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBankAccountRequest;
use App\Http\Requests\Api\UpdateBankAccountRequest;
use App\Http\Resources\Api\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BankAccount::class);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'is_default' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;

        $bankAccounts = BankAccount::query()
            ->with(['user', 'organization'])
            ->visibleTo($request->user())
            ->when($validated['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($validated['organization_id'] ?? null, fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when(
                array_key_exists('is_default', $validated) && $validated['is_default'] !== null,
                fn ($query) => $query->where('is_default', $validated['is_default'])
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return BankAccountResource::collection($bankAccounts)->response();
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $bankAccount = DB::transaction(function () use ($request): BankAccount {
            $bankAccount = BankAccount::query()->create($request->bankAccountAttributes());

            if ($bankAccount->is_default) {
                $bankAccount->clearOtherDefaultsForSameOwner();
            }

            return $bankAccount;
        });

        $bankAccount->load(['user', 'organization']);

        return response()->json([
            'data' => new BankAccountResource($bankAccount),
        ], Response::HTTP_CREATED);
    }

    public function show(BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('view', $bankAccount);

        $bankAccount->loadMissing(['user', 'organization']);

        return response()->json([
            'data' => new BankAccountResource($bankAccount),
        ]);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        DB::transaction(function () use ($request, $bankAccount): void {
            $bankAccount->fill($request->bankAccountAttributes());
            $bankAccount->save();

            if ($bankAccount->is_default) {
                $bankAccount->clearOtherDefaultsForSameOwner();
            }
        });

        $bankAccount->load(['user', 'organization']);

        return response()->json([
            'data' => new BankAccountResource($bankAccount),
        ]);
    }

    public function destroy(BankAccount $bankAccount): Response
    {
        $this->authorize('delete', $bankAccount);

        $bankAccount->delete();

        return response()->noContent();
    }
}
