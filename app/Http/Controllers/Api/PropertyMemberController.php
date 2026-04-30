<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncPropertyMembersRequest;
use App\Http\Resources\Api\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

class PropertyMemberController extends Controller
{
    public function sync(SyncPropertyMembersRequest $request, Property $property): JsonResponse
    {
        $this->authorize('manageMembers', $property);

        $members = collect($request->validated('members'))
            ->mapWithKeys(function (array $member): array {
                return [
                    $member['user_id'] => [
                        'role' => $member['role'],
                        'start_date' => $member['start_date'] ?? null,
                        'end_date' => $member['end_date'] ?? null,
                    ],
                ];
            })
            ->all();

        $property->users()->sync($members);
        $property->load(['address', 'users:id,name,email']);

        return response()->json([
            'data' => new PropertyResource($property),
        ]);
    }
}
