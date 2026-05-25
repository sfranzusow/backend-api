<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShowDocumentTemplatePlaceholdersRequest;
use App\Http\Resources\Api\DocumentTemplatePlaceholderResource;
use App\Models\DocumentTemplate;
use Illuminate\Http\JsonResponse;

class DocumentTemplatePlaceholderController extends Controller
{
    public function __invoke(ShowDocumentTemplatePlaceholdersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $placeholders = DocumentTemplate::placeholderDefinitionsFor($validated['document_type']);

        return DocumentTemplatePlaceholderResource::collection($placeholders)->response();
    }
}
