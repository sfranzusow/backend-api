<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRentalAgreementDocumentRequest;
use App\Http\Resources\Api\DocumentResource;
use App\Models\Document;
use App\Models\RentalAgreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RentalAgreementDocumentController extends Controller
{
    public function index(Request $request, RentalAgreement $rentalAgreement): JsonResponse
    {
        $this->authorize('view', $rentalAgreement);

        $documents = $rentalAgreement->documents()
            ->with(['template', 'latestVersion.files', 'creator:id,name,email'])
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
}
