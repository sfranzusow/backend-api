<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDocumentLayoutTemplateRequest;
use App\Http\Requests\Api\UpdateDocumentLayoutTemplateRequest;
use App\Http\Resources\Api\DocumentLayoutTemplateResource;
use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentLayoutTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocumentLayoutTemplate::class);

        $validated = $request->validate([
            'owner_type' => ['nullable', 'string', Rule::in(DocumentLayoutTemplate::ownerTypes())],
            'owner_id' => ['nullable', 'integer', 'min:1'],
            'document_type' => ['nullable', 'string', Rule::in(DocumentTemplate::documentTypes())],
            'locale' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', Rule::in(DocumentLayoutTemplate::statuses())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $authUser = $request->user();
        $isAdmin = $authUser?->hasRole(RoleName::Admin->value) === true;
        $perPage = $validated['per_page'] ?? 15;
        $ownerType = isset($validated['owner_type'])
            ? DocumentLayoutTemplate::ownerClassFor($validated['owner_type'])
            : null;

        $layouts = DocumentLayoutTemplate::query()
            ->when(! $isAdmin, function ($query) use ($authUser): void {
                $query->where(function ($query) use ($authUser): void {
                    if ($authUser?->organization_id !== null) {
                        $query->where(function ($query) use ($authUser): void {
                            $query
                                ->where('owner_type', Organization::class)
                                ->where('owner_id', $authUser->organization_id);
                        });
                    }

                    $query->orWhere(function ($query) use ($authUser): void {
                        $query
                            ->where('owner_type', User::class)
                            ->where('owner_id', $authUser?->id);
                    });
                });
            })
            ->when($ownerType, fn ($query, string $type) => $query->where('owner_type', $type))
            ->when($validated['owner_id'] ?? null, fn ($query, int $ownerId) => $query->where('owner_id', $ownerId))
            ->when($validated['document_type'] ?? null, fn ($query, string $documentType) => $query->where('document_type', $documentType))
            ->when($validated['locale'] ?? null, fn ($query, string $locale) => $query->where('locale', $locale))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return DocumentLayoutTemplateResource::collection($layouts)->response();
    }

    public function store(StoreDocumentLayoutTemplateRequest $request): JsonResponse
    {
        $attributes = $this->attributesWithDefaults($request->layoutAttributes());
        $requestedStatus = $attributes['status'];
        $attributes['status'] = DocumentLayoutTemplate::STATUS_DRAFT;
        $attributes['created_by_id'] = $request->user()?->id;

        $layout = DocumentLayoutTemplate::query()->create($attributes);

        if ($requestedStatus === DocumentLayoutTemplate::STATUS_ACTIVE) {
            $layout = $this->activateLayout($layout);
        }

        return response()->json([
            'data' => new DocumentLayoutTemplateResource($layout),
        ], Response::HTTP_CREATED);
    }

    public function show(DocumentLayoutTemplate $documentLayoutTemplate): JsonResponse
    {
        $this->authorize('view', $documentLayoutTemplate);

        return response()->json([
            'data' => new DocumentLayoutTemplateResource($documentLayoutTemplate),
        ]);
    }

    public function update(UpdateDocumentLayoutTemplateRequest $request, DocumentLayoutTemplate $documentLayoutTemplate): JsonResponse
    {
        $attributes = $request->layoutAttributes();
        $requestedStatus = $attributes['status'] ?? null;

        if ($requestedStatus === DocumentLayoutTemplate::STATUS_ACTIVE) {
            $attributes['status'] = DocumentLayoutTemplate::STATUS_DRAFT;
        }

        $documentLayoutTemplate->fill($attributes);
        $documentLayoutTemplate->save();

        if ($requestedStatus === DocumentLayoutTemplate::STATUS_ACTIVE) {
            $documentLayoutTemplate = $this->activateLayout($documentLayoutTemplate);
        }

        return response()->json([
            'data' => new DocumentLayoutTemplateResource($documentLayoutTemplate),
        ]);
    }

    public function activate(Request $request, DocumentLayoutTemplate $documentLayoutTemplate): JsonResponse
    {
        $this->authorize('activate', $documentLayoutTemplate);

        $documentLayoutTemplate = $this->activateLayout($documentLayoutTemplate);

        return response()->json([
            'data' => new DocumentLayoutTemplateResource($documentLayoutTemplate),
        ]);
    }

    public function destroy(DocumentLayoutTemplate $documentLayoutTemplate): Response
    {
        $this->authorize('delete', $documentLayoutTemplate);

        if ($documentLayoutTemplate->status === DocumentLayoutTemplate::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'Active document layout templates must be archived before deletion.',
            ]);
        }

        $documentLayoutTemplate->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributesWithDefaults(array $attributes): array
    {
        $attributes['locale'] ??= 'de-DE';
        $attributes['version'] ??= 1;
        $attributes['status'] ??= DocumentLayoutTemplate::STATUS_DRAFT;
        $attributes['header_enabled'] ??= false;
        $attributes['footer_enabled'] ??= false;
        $attributes['page_numbers_enabled'] ??= true;

        return $attributes;
    }

    private function activateLayout(DocumentLayoutTemplate $layout): DocumentLayoutTemplate
    {
        return DB::transaction(function () use ($layout): DocumentLayoutTemplate {
            DocumentLayoutTemplate::query()
                ->where('owner_type', $layout->owner_type)
                ->where('owner_id', $layout->owner_id)
                ->where('document_type', $layout->document_type)
                ->where('locale', $layout->locale)
                ->where('status', DocumentLayoutTemplate::STATUS_ACTIVE)
                ->whereKeyNot($layout->id)
                ->update([
                    'status' => DocumentLayoutTemplate::STATUS_ARCHIVED,
                ]);

            $layout->forceFill([
                'status' => DocumentLayoutTemplate::STATUS_ACTIVE,
            ])->save();

            return $layout->refresh();
        });
    }
}
