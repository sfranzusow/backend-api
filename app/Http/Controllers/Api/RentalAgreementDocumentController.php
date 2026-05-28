<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRentalAgreementDocumentRequest;
use App\Http\Resources\Api\DocumentResource;
use App\Models\Document;
use App\Models\RentalAgreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RentalAgreementDocumentController extends Controller
{
    /**
     * @var list<string>
     */
    private const BASE_RESPONSE_RELATIONS = [
        'template',
        'latestVersion.files',
        'creator:id,name,email',
    ];

    /**
     * @var list<string>
     */
    private const OPTIONAL_RESPONSE_INCLUDES = [
        'reminders',
    ];

    public function index(Request $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        $this->authorize('view', $rentalAgreement);

        $validated = $this->validateIndexRequest($request);
        $authUser = $request->user();
        $limitToTenantVisibleStatuses = $authUser?->hasRole(RoleName::Tenant->value) === true
            && ! $authUser->hasRole(RoleName::Landlord->value);

        $documents = $rentalAgreement->documents()
            ->with($this->responseRelations($request, $validated, $limitToTenantVisibleStatuses))
            ->when($limitToTenantVisibleStatuses, function ($query) {
                $query->whereIn('status', Document::tenantVisibleStatuses());
            })
            ->when($validated['status'] ?? null, function ($query, string $status): void {
                $query->where('status', $status);
            })
            ->when($validated['document_type'] ?? null, function ($query, string $documentType): void {
                $query->where('document_type', $documentType);
            })
            ->latest('id')
            ->get();

        return DocumentResource::collection($documents)->response();
    }

    public function store(StoreRentalAgreementDocumentRequest $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        $validated = $request->validated();

        $document = $rentalAgreement->documents()->create([
            'document_template_id' => $validated['document_template_id'] ?? null,
            'document_type' => $validated['document_type'],
            'status' => Document::STATUS_DRAFT,
            'title' => $validated['title'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
            'created_by_id' => $request->user()?->id,
        ]);

        $document->load(['template', 'latestVersion.files', 'creator:id,name,email']);

        return response()->json([
            'data' => new DocumentResource($document),
        ], Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIndexRequest(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(Document::statuses())],
            'document_type' => ['nullable', 'string', 'max:255'],
            'include' => ['nullable', 'string', 'max:255'],
        ]);

        $this->requestedIncludes($validated);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int|string, mixed>
     */
    private function responseRelations(Request $request, array $validated, bool $limitToAssignedTenant): array
    {
        $relations = self::BASE_RESPONSE_RELATIONS;
        $includes = $this->requestedIncludes($validated);
        $authUser = $request->user();

        if (in_array('reminders', $includes, true)) {
            $relations['reminders'] = function ($query) use ($authUser, $limitToAssignedTenant): void {
                $query
                    ->with(['remindable', 'creator:id,name,email', 'assignee:id,name,email'])
                    ->when($limitToAssignedTenant, function ($query) use ($authUser): void {
                        $query->where('assigned_to_id', $authUser->id);
                    });
            };
        }

        return $relations;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function requestedIncludes(array $validated): array
    {
        $include = $validated['include'] ?? '';

        if (! is_string($include) || $include === '') {
            return [];
        }

        $includes = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $include)),
            fn (string $value): bool => $value !== '',
        )));

        $invalidIncludes = array_diff($includes, self::OPTIONAL_RESPONSE_INCLUDES);

        if ($invalidIncludes !== []) {
            throw ValidationException::withMessages([
                'include' => 'The selected include is invalid.',
            ]);
        }

        return $includes;
    }
}
