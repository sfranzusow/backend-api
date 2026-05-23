<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDocumentTemplateRequest;
use App\Http\Requests\Api\UpdateDocumentTemplateRequest;
use App\Http\Resources\Api\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocumentTemplate::class);

        $validated = $request->validate([
            'document_type' => ['nullable', 'string', Rule::in(DocumentTemplate::documentTypes())],
            'template_type' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', Rule::in(DocumentTemplate::statuses())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $isAdmin = $request->user()?->can('create', DocumentTemplate::class) === true;
        $perPage = $validated['per_page'] ?? 15;

        $templates = DocumentTemplate::query()
            ->when(! $isAdmin, fn ($query) => $query->where('status', DocumentTemplate::STATUS_ACTIVE))
            ->when($validated['document_type'] ?? null, fn ($query, $documentType) => $query->where('document_type', $documentType))
            ->when($validated['template_type'] ?? null, fn ($query, $templateType) => $query->where('template_type', $templateType))
            ->when($validated['locale'] ?? null, fn ($query, $locale) => $query->where('locale', $locale))
            ->when($isAdmin && ($validated['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return DocumentTemplateResource::collection($templates)->response();
    }

    public function store(StoreDocumentTemplateRequest $request): JsonResponse
    {
        $attributes = $this->attributesWithDefaults($request->templateAttributes());
        $requestedStatus = $attributes['status'];
        $attributes['status'] = DocumentTemplate::STATUS_DRAFT;
        $attributes['created_by_id'] = $request->user()?->id;

        $template = DocumentTemplate::query()->create($attributes);

        if ($requestedStatus === DocumentTemplate::STATUS_ACTIVE) {
            $template = $this->activateTemplate($template);
        }

        return response()->json([
            'data' => new DocumentTemplateResource($template),
        ], Response::HTTP_CREATED);
    }

    public function show(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('view', $documentTemplate);

        return response()->json([
            'data' => new DocumentTemplateResource($documentTemplate),
        ]);
    }

    public function update(UpdateDocumentTemplateRequest $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $attributes = $request->templateAttributes();
        $requestedStatus = $attributes['status'] ?? null;

        if ($requestedStatus === DocumentTemplate::STATUS_ACTIVE) {
            $attributes['status'] = DocumentTemplate::STATUS_DRAFT;
        }

        $documentTemplate->fill($attributes);
        $documentTemplate->save();

        if ($requestedStatus === DocumentTemplate::STATUS_ACTIVE) {
            $documentTemplate = $this->activateTemplate($documentTemplate);
        }

        return response()->json([
            'data' => new DocumentTemplateResource($documentTemplate),
        ]);
    }

    public function activate(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('activate', $documentTemplate);

        $documentTemplate = $this->activateTemplate($documentTemplate);

        return response()->json([
            'data' => new DocumentTemplateResource($documentTemplate),
        ]);
    }

    public function destroy(DocumentTemplate $documentTemplate): Response
    {
        $this->authorize('delete', $documentTemplate);

        if ($documentTemplate->status === DocumentTemplate::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'Active document templates must be archived before deletion.',
            ]);
        }

        $documentTemplate->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributesWithDefaults(array $attributes): array
    {
        $attributes['template_type'] ??= 'default';
        $attributes['locale'] ??= 'de-DE';
        $attributes['version'] ??= 1;
        $attributes['status'] ??= DocumentTemplate::STATUS_DRAFT;

        return $attributes;
    }

    private function activateTemplate(DocumentTemplate $template): DocumentTemplate
    {
        return DB::transaction(function () use ($template): DocumentTemplate {
            DocumentTemplate::query()
                ->where('document_type', $template->document_type)
                ->where('template_type', $template->template_type)
                ->where('locale', $template->locale)
                ->where('status', DocumentTemplate::STATUS_ACTIVE)
                ->whereKeyNot($template->id)
                ->update([
                    'status' => DocumentTemplate::STATUS_ARCHIVED,
                ]);

            $template->forceFill([
                'status' => DocumentTemplate::STATUS_ACTIVE,
            ])->save();

            return $template->refresh();
        });
    }
}
