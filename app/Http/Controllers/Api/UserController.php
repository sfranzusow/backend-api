<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['roles:id,name']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(['id', 'name', 'email'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'asc';

        $users = User::query()
            ->select(['id', 'name', 'email', 'created_at', 'updated_at'])
            ->with('roles:id,name')
            ->filter($validated)
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return UserResource::collection($users)->response();
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['name', 'email', 'password']);

        $user = User::query()->create($data);

        if ($request->filled('roles')) {
            $this->authorize('assignRoles', $user);
            $user->syncRoles($request->validated('roles'));
        } else {
            $user->assignRole(RoleName::User->value);
        }

        $user->load('roles:id,name');

        return response()->json([
            'data' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->loadMissing(['roles:id,name']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $attributes = $request->safe()->only(['name', 'email']);

        if (! empty($attributes)) {
            $user->fill($attributes);
        }

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        if ($request->has('roles')) {
            $this->authorize('assignRoles', $user);
            $user->syncRoles($request->validated('roles'));
        }

        $user->load('roles:id,name');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->noContent();
    }
}
