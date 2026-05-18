<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DocumentResource;
use App\Models\Document;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function show(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->loadMissing(['template', 'latestVersion.files', 'creator:id,name,email']);

        return response()->json([
            'data' => new DocumentResource($document),
        ]);
    }
}
