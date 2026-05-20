<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSignedDocumentUploadRequest;
use App\Http\Resources\Api\DocumentResource;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\Documents\DocumentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * @var list<string>
     */
    private const BASE_RESPONSE_RELATIONS = [
        'template',
        'latestVersion.files',
        'creator:id,name,email',
    ];

    public function __construct(
        private DocumentWorkflowService $documents,
    ) {}

    public function show(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->loadMissing($this->responseRelations($request));

        return response()->json([
            'data' => new DocumentResource($document),
        ]);
    }

    public function generate(Request $request, Document $document): JsonResponse
    {
        $this->authorize('generate', $document);

        $this->documents->generate($document, $request->user());

        return $this->documentResponse($request, $document, Response::HTTP_CREATED);
    }

    public function share(Request $request, Document $document): JsonResponse
    {
        $this->authorize('share', $document);

        $this->documents->share($document);

        return $this->documentResponse($request, $document);
    }

    public function voidDocument(Request $request, Document $document): JsonResponse
    {
        $this->authorize('voidDocument', $document);

        $this->documents->void($document);

        return $this->documentResponse($request, $document);
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        $file = $this->documents->generatedFile($document);

        if (! $file instanceof DocumentFile) {
            abort(Response::HTTP_NOT_FOUND, 'No generated PDF is available for this document.');
        }

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name ?? 'document-'.$document->id.'.pdf',
            ['Content-Type' => $file->mime_type ?? 'application/pdf']
        );
    }

    public function signedUpload(StoreSignedDocumentUploadRequest $request, Document $document): JsonResponse
    {
        $validated = $request->validated();
        $uploadedFile = $request->file('file');

        if (! $uploadedFile instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'A signed document file is required.',
            ]);
        }

        $this->documents->uploadSigned(
            $document,
            $uploadedFile,
            $request->user(),
            $validated['metadata'] ?? null,
        );

        return $this->documentResponse($request, $document, Response::HTTP_CREATED);
    }

    public function signedDownload(Document $document): StreamedResponse
    {
        $this->authorize('downloadSigned', $document);

        $file = $this->documents->signedFile($document);

        if (! $file instanceof DocumentFile) {
            abort(Response::HTTP_NOT_FOUND, 'No signed upload is available for this document.');
        }

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name ?? 'signed-document-'.$document->id,
            ['Content-Type' => $file->mime_type ?? 'application/octet-stream']
        );
    }

    private function documentResponse(Request $request, Document $document, int $status = Response::HTTP_OK): JsonResponse
    {
        $document->refresh()->load($this->responseRelations($request));

        return response()->json([
            'data' => new DocumentResource($document),
        ], $status);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function responseRelations(Request $request): array
    {
        $authUser = $request->user();
        $limitToAssignedTenant = $authUser?->hasRole(RoleName::Tenant->value) === true
            && ! $authUser->hasRole(RoleName::Landlord->value);

        return [
            ...self::BASE_RESPONSE_RELATIONS,
            'reminders' => function ($query) use ($authUser, $limitToAssignedTenant): void {
                $query
                    ->with(['creator:id,name,email', 'assignee:id,name,email'])
                    ->when($limitToAssignedTenant, function ($query) use ($authUser): void {
                        $query->where('assigned_to_id', $authUser->id);
                    });
            },
        ];
    }
}
