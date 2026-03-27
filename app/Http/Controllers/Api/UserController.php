<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user(),
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
            ->select(['id', 'name', 'email'])
            ->with('roles:id,name')
            ->filter($validated)
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($users);
    }
}