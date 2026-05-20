<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSignedDocumentUploadRequest;
use App\Http\Resources\Api\DocumentResource;
use App\Models\Address;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function generate(Request $request, Document $document): JsonResponse
    {
        $this->authorize('generate', $document);

        DB::transaction(function () use ($request, $document): void {
            $document->loadMissing(['documentable', 'template']);

            $rentalAgreement = $document->documentable;

            if (! $rentalAgreement instanceof RentalAgreement) {
                throw ValidationException::withMessages([
                    'document' => 'Document generation is currently only available for rental agreements.',
                ]);
            }

            $rentalAgreement->loadMissing(['property.address', 'landlord', 'tenant']);
            $template = $this->activeTemplateFor($document);

            if (! $template instanceof DocumentTemplate) {
                throw ValidationException::withMessages([
                    'document_template_id' => 'No active document template is available for this document type.',
                ]);
            }

            $generatedAt = now();
            $dataSnapshot = $this->rentalAgreementDataSnapshot($document, $template, $rentalAgreement);
            $contentSnapshot = $this->renderTemplateContent($template->content, $dataSnapshot);
            $versionNumber = ((int) $document->versions()->lockForUpdate()->max('version_number')) + 1;

            $version = $document->versions()->create([
                'document_template_id' => $template->id,
                'version_number' => $versionNumber,
                'status' => DocumentVersion::STATUS_GENERATED,
                'title' => $document->title ?? $template->name,
                'content_snapshot' => $contentSnapshot,
                'template_snapshot' => $this->templateSnapshot($template),
                'data_snapshot' => $dataSnapshot,
                'metadata' => [
                    'renderer' => 'basic_pdf',
                ],
                'generated_by_id' => $request->user()?->id,
                'generated_at' => $generatedAt,
            ]);

            $pdfContents = $this->renderBasicPdf($contentSnapshot);
            $path = 'documents/'.$document->id.'/versions/'.$version->version_number.'/generated.pdf';
            $disk = $this->documentsDisk();

            if (! Storage::disk($disk)->put($path, $pdfContents)) {
                throw new RuntimeException('The generated PDF could not be stored.');
            }

            $version->files()->create([
                'file_type' => DocumentFile::TYPE_GENERATED_PDF,
                'disk' => $disk,
                'path' => $path,
                'original_name' => 'document-'.$document->id.'-v'.$version->version_number.'.pdf',
                'mime_type' => 'application/pdf',
                'size' => strlen($pdfContents),
                'checksum' => hash('sha256', $pdfContents),
                'metadata' => [
                    'renderer' => 'basic_pdf',
                ],
                'uploaded_by_id' => $request->user()?->id,
                'uploaded_at' => $generatedAt,
            ]);

            $document->forceFill([
                'document_template_id' => $template->id,
                'status' => Document::STATUS_GENERATED,
            ])->save();
        });

        $document->refresh()->load(['template', 'latestVersion.files', 'creator:id,name,email']);

        return response()->json([
            'data' => new DocumentResource($document),
        ], Response::HTTP_CREATED);
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        $document->loadMissing('latestVersion.files');

        $file = $document->latestVersion?->files
            ->firstWhere('file_type', DocumentFile::TYPE_GENERATED_PDF);

        if (! $file instanceof DocumentFile || ! Storage::disk($file->disk)->exists($file->path)) {
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

        DB::transaction(function () use ($request, $document, $validated): void {
            $version = $document->latestVersion()->lockForUpdate()->first();

            if (
                ! $version instanceof DocumentVersion
                || ! in_array($version->status, [
                    DocumentVersion::STATUS_GENERATED,
                    DocumentVersion::STATUS_SHARED,
                    DocumentVersion::STATUS_SIGNED_UPLOADED,
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'document' => 'A generated document version is required before uploading a signed file.',
                ]);
            }

            $uploadedFile = $request->file('file');
            $uploadedAt = now();
            $extension = $uploadedFile->extension() ?: $uploadedFile->getClientOriginalExtension();
            $filename = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
            $disk = $this->documentsDisk();
            $path = Storage::disk($disk)->putFileAs(
                'documents/'.$document->id.'/versions/'.$version->version_number.'/signed',
                $uploadedFile,
                $filename
            );

            if (! is_string($path)) {
                throw new RuntimeException('The signed document could not be stored.');
            }

            $realPath = $uploadedFile->getRealPath();

            if (! is_string($realPath)) {
                throw new RuntimeException('The signed document checksum could not be calculated.');
            }

            $checksum = hash_file('sha256', $realPath);

            if (! is_string($checksum)) {
                throw new RuntimeException('The signed document checksum could not be calculated.');
            }

            $version->files()->create([
                'file_type' => DocumentFile::TYPE_SIGNED_UPLOAD,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
                'checksum' => $checksum,
                'metadata' => $validated['metadata'] ?? null,
                'uploaded_by_id' => $request->user()?->id,
                'uploaded_at' => $uploadedAt,
            ]);

            $version->forceFill([
                'status' => DocumentVersion::STATUS_SIGNED_UPLOADED,
            ])->save();

            $document->forceFill([
                'status' => Document::STATUS_SIGNED_UPLOADED,
            ])->save();
        });

        $document->refresh()->load(['template', 'latestVersion.files', 'creator:id,name,email']);

        return response()->json([
            'data' => new DocumentResource($document),
        ], Response::HTTP_CREATED);
    }

    public function signedDownload(Document $document): StreamedResponse
    {
        $this->authorize('downloadSigned', $document);

        $document->loadMissing('latestVersion');

        $file = $document->latestVersion?->files()
            ->where('file_type', DocumentFile::TYPE_SIGNED_UPLOAD)
            ->latest('id')
            ->first();

        if (! $file instanceof DocumentFile || ! Storage::disk($file->disk)->exists($file->path)) {
            abort(Response::HTTP_NOT_FOUND, 'No signed upload is available for this document.');
        }

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name ?? 'signed-document-'.$document->id,
            ['Content-Type' => $file->mime_type ?? 'application/octet-stream']
        );
    }

    private function documentsDisk(): string
    {
        $disk = config('documents.disk');

        if (is_string($disk) && $disk !== '') {
            return $disk;
        }

        $defaultDisk = config('filesystems.default');

        return is_string($defaultDisk) && $defaultDisk !== '' ? $defaultDisk : 'local';
    }

    private function activeTemplateFor(Document $document): ?DocumentTemplate
    {
        $template = $document->template;

        if (
            $template instanceof DocumentTemplate
            && $template->status === DocumentTemplate::STATUS_ACTIVE
            && $template->document_type === $document->document_type
        ) {
            return $template;
        }

        return DocumentTemplate::query()
            ->where('document_type', $document->document_type)
            ->where('status', DocumentTemplate::STATUS_ACTIVE)
            ->latest('version')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function rentalAgreementDataSnapshot(
        Document $document,
        DocumentTemplate $template,
        RentalAgreement $rentalAgreement
    ): array {
        $property = $rentalAgreement->property;
        $address = $property?->address;

        return [
            'document' => [
                'id' => $document->id,
                'title' => $document->title ?? $template->name,
                'document_type' => $document->document_type,
            ],
            'rental_agreement' => [
                'id' => $rentalAgreement->id,
                'date_from' => $rentalAgreement->date_from?->toDateString(),
                'date_to' => $rentalAgreement->date_to?->toDateString(),
                'rent_cold' => $rentalAgreement->rent_cold,
                'rent_warm' => $rentalAgreement->rent_warm,
                'service_charges' => $rentalAgreement->service_charges,
                'deposit' => $rentalAgreement->deposit,
                'currency' => $rentalAgreement->currency,
                'status' => $rentalAgreement->status,
                'notes' => $rentalAgreement->notes,
            ],
            'property' => [
                'id' => $property?->id,
                'unit_number' => $property?->unit_number,
                'type' => $property?->type,
                'address' => $this->formatAddress($address),
                'address_details' => [
                    'street' => $address?->street,
                    'house_number' => $address?->house_number,
                    'zip_code' => $address?->zip_code,
                    'city' => $address?->city,
                    'country' => $address?->country,
                ],
            ],
            'landlord' => $this->userSnapshot($rentalAgreement->landlord),
            'tenant' => $this->userSnapshot($rentalAgreement->tenant),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSnapshot(DocumentTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'document_type' => $template->document_type,
            'template_type' => $template->template_type,
            'locale' => $template->locale,
            'version' => $template->version,
            'placeholders' => $template->placeholders,
            'metadata' => $template->metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userSnapshot(?User $user): array
    {
        return [
            'id' => $user?->id,
            'name' => $user?->name,
            'email' => $user?->email,
            'phone_number' => $user?->phone_number,
            'address_street' => $user?->address_street,
            'address_house_number' => $user?->address_house_number,
            'address_zip_code' => $user?->address_zip_code,
            'address_city' => $user?->address_city,
            'address_country' => $user?->address_country,
        ];
    }

    private function formatAddress(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $street = trim(implode(' ', array_filter([
            $address->street,
            $address->house_number,
        ])));

        $city = trim(implode(' ', array_filter([
            $address->zip_code,
            $address->city,
        ])));

        return trim(implode(', ', array_filter([
            $street,
            $city,
            $address->country,
        ])));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderTemplateContent(?string $content, array $data): string
    {
        $templateContent = $content ?: '<h1>{{ document.title }}</h1>';

        return preg_replace_callback(
            '/{{\s*([A-Za-z0-9_.]+)\s*}}/',
            fn (array $matches): string => e($this->stringValue(data_get($data, $matches[1]))),
            $templateContent
        ) ?? $templateContent;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function renderBasicPdf(string $content): string
    {
        $plainText = html_entity_decode(
            strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</h1>', '</h2>'], "\n", $content)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $plainText = Str::of($plainText)
            ->replaceMatches('/[ \t]+/', ' ')
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim()
            ->toString();

        $lines = [];

        foreach (explode("\n", Str::ascii($plainText)) as $line) {
            foreach (explode("\n", wordwrap($line, 88, "\n", true)) as $wrappedLine) {
                $lines[] = $wrappedLine;
            }
        }

        $stream = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";

        foreach (array_slice($lines, 0, 52) as $line) {
            $stream .= '('.$this->escapePdfText($line).") Tj\nT*\n";
        }

        $stream .= 'ET';

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj",
            "4 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream\nendobj",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
