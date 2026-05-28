<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDocumentReminderRequest;
use App\Http\Requests\Api\UpdateDocumentReminderRequest;
use App\Http\Resources\Api\DocumentReminderResource;
use App\Models\Document;
use App\Models\DocumentReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentReminderController extends Controller
{
    public function index(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $authUser = $request->user();
        $limitToAssignedTenant = $authUser?->hasRole(RoleName::Tenant->value) === true
            && ! $authUser->hasRole(RoleName::Landlord->value);

        $reminders = $document->reminders()
            ->with(['document.documentable', 'creator:id,name,email', 'assignee:id,name,email'])
            ->when($limitToAssignedTenant, function ($query) use ($authUser) {
                $query->where('assigned_to_id', $authUser->id);
            })
            ->get();

        return response()->json([
            'data' => DocumentReminderResource::collection($reminders),
        ]);
    }

    public function store(StoreDocumentReminderRequest $request, Document $document): JsonResponse
    {
        $reminder = $document->reminders()->create([
            ...$request->validated(),
            'status' => DocumentReminder::STATUS_PENDING,
            'created_by_id' => $request->user()?->id,
        ]);

        $reminder->load(['document.documentable', 'creator:id,name,email', 'assignee:id,name,email']);

        return response()->json([
            'data' => new DocumentReminderResource($reminder),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateDocumentReminderRequest $request, DocumentReminder $documentReminder): JsonResponse
    {
        $validated = $request->validated();

        if (($validated['status'] ?? null) === DocumentReminder::STATUS_DONE) {
            $validated['completed_at'] ??= now();
        } elseif (array_key_exists('status', $validated)) {
            $validated['completed_at'] ??= null;
        }

        $documentReminder->forceFill($validated)->save();
        $documentReminder->load(['document.documentable', 'creator:id,name,email', 'assignee:id,name,email']);

        return response()->json([
            'data' => new DocumentReminderResource($documentReminder),
        ]);
    }

    public function destroy(DocumentReminder $documentReminder): Response
    {
        $this->authorize('delete', $documentReminder);

        $documentReminder->delete();

        return response()->noContent();
    }
}
