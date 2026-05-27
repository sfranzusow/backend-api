<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DocumentWorkflowService
{
    public function __construct(
        private DompdfDocumentRenderer $pdfRenderer,
        private RentalAgreementDocumentSnapshotFactory $snapshotFactory,
        private DocumentTemplateRenderer $templateRenderer,
        private DocumentLayoutTemplateResolver $layoutResolver,
    ) {}

    public function generate(Document $document, ?User $user): Document
    {
        return DB::transaction(function () use ($document, $user): Document {
            $document->loadMissing(['documentable', 'template']);

            if (! $document->canTransitionToStatus(Document::STATUS_GENERATED)) {
                throw ValidationException::withMessages([
                    'status' => 'This document cannot be generated from its current status.',
                ]);
            }

            $rentalAgreement = $document->documentable;

            if (! $rentalAgreement instanceof RentalAgreement) {
                throw ValidationException::withMessages([
                    'document' => 'Document generation is currently only available for rental agreements.',
                ]);
            }

            $rentalAgreement->loadMissing(['property.address', 'landlord.organization', 'tenant', 'bankAccount']);
            $template = $this->activeTemplateFor($document);

            if (! $template instanceof DocumentTemplate) {
                throw ValidationException::withMessages([
                    'document_template_id' => 'No active document template is available for this document type.',
                ]);
            }

            $generatedAt = now();
            $previousVersion = $document->latestVersion()->lockForUpdate()->first();
            $versionNumber = ((int) $document->versions()->lockForUpdate()->max('version_number')) + 1;
            $dataSnapshot = $this->snapshotFactory->make($document, $template, $rentalAgreement, $versionNumber, $generatedAt);
            $contentSnapshot = $this->templateRenderer->render($template->content, $dataSnapshot);
            $layout = $this->layoutResolver->activeFor($document, $template, $rentalAgreement);
            $renderedLayout = $this->renderedLayout($layout, $dataSnapshot);

            if (
                $previousVersion instanceof DocumentVersion
                && $previousVersion->canTransitionToStatus(DocumentVersion::STATUS_VOID)
                && $previousVersion->status !== DocumentVersion::STATUS_VOID
            ) {
                $previousVersion->forceFill([
                    'status' => DocumentVersion::STATUS_VOID,
                ])->save();
            }

            $version = $document->versions()->create([
                'document_template_id' => $template->id,
                'document_layout_template_id' => $layout?->id,
                'version_number' => $versionNumber,
                'status' => DocumentVersion::STATUS_GENERATED,
                'title' => $document->title ?? $template->name,
                'content_snapshot' => $contentSnapshot,
                'template_snapshot' => $this->templateSnapshot($template),
                'layout_snapshot' => $this->layoutSnapshot($layout),
                'data_snapshot' => $dataSnapshot,
                'metadata' => [
                    'renderer' => 'dompdf',
                ],
                'generated_by_id' => $user?->id,
                'generated_at' => $generatedAt,
            ]);

            $pdfContents = $this->pdfRenderer->render($contentSnapshot, $renderedLayout);
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
                    'renderer' => 'dompdf',
                    'document_layout_template_id' => $layout?->id,
                ],
                'uploaded_by_id' => $user?->id,
                'uploaded_at' => $generatedAt,
            ]);

            $document->forceFill([
                'document_template_id' => $template->id,
                'status' => Document::STATUS_GENERATED,
            ])->save();

            return $document;
        });
    }

    public function share(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $version = $document->latestVersion()->lockForUpdate()->first();

            if (! ($version instanceof DocumentVersion)) {
                throw ValidationException::withMessages([
                    'document' => 'A generated document version is required before sharing.',
                ]);
            }

            if (
                ! $document->canTransitionToStatus(Document::STATUS_SHARED)
                || ! $version->canTransitionToStatus(DocumentVersion::STATUS_SHARED)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'This document cannot be shared from its current status.',
                ]);
            }

            $version->forceFill([
                'status' => DocumentVersion::STATUS_SHARED,
            ])->save();

            $document->forceFill([
                'status' => Document::STATUS_SHARED,
            ])->save();

            return $document;
        });
    }

    public function void(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $version = $document->latestVersion()->lockForUpdate()->first();

            if (! $document->canTransitionToStatus(Document::STATUS_VOID)) {
                throw ValidationException::withMessages([
                    'status' => 'This document cannot be voided from its current status.',
                ]);
            }

            if ($version instanceof DocumentVersion && $version->canTransitionToStatus(DocumentVersion::STATUS_VOID)) {
                $version->forceFill([
                    'status' => DocumentVersion::STATUS_VOID,
                ])->save();
            }

            $document->forceFill([
                'status' => Document::STATUS_VOID,
            ])->save();

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function uploadSigned(Document $document, UploadedFile $uploadedFile, ?User $user, ?array $metadata = null): Document
    {
        return DB::transaction(function () use ($document, $uploadedFile, $user, $metadata): Document {
            $version = $document->latestVersion()->lockForUpdate()->first();

            if (
                ! $document->canTransitionToStatus(Document::STATUS_SIGNED_UPLOADED)
                || ! ($version instanceof DocumentVersion)
                || ! $version->canTransitionToStatus(DocumentVersion::STATUS_SIGNED_UPLOADED)
            ) {
                throw ValidationException::withMessages([
                    'document' => 'A generated document version is required before uploading a signed file.',
                ]);
            }

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
                'metadata' => $metadata,
                'uploaded_by_id' => $user?->id,
                'uploaded_at' => $uploadedAt,
            ]);

            $version->forceFill([
                'status' => DocumentVersion::STATUS_SIGNED_UPLOADED,
            ])->save();

            $document->forceFill([
                'status' => Document::STATUS_SIGNED_UPLOADED,
            ])->save();

            return $document;
        });
    }

    public function generatedFile(Document $document): ?DocumentFile
    {
        $document->loadMissing('latestVersion.files');

        $file = $document->latestVersion?->files
            ->firstWhere('file_type', DocumentFile::TYPE_GENERATED_PDF);

        if (
            $document->latestVersion?->status === DocumentVersion::STATUS_VOID
            || ! $file instanceof DocumentFile
            || ! Storage::disk($file->disk)->exists($file->path)
        ) {
            return null;
        }

        return $file;
    }

    public function signedFile(Document $document): ?DocumentFile
    {
        $document->loadMissing('latestVersion');

        $file = $document->latestVersion?->files()
            ->where('file_type', DocumentFile::TYPE_SIGNED_UPLOAD)
            ->latest('id')
            ->first();

        if (
            $document->latestVersion?->status === DocumentVersion::STATUS_VOID
            || ! $file instanceof DocumentFile
            || ! Storage::disk($file->disk)->exists($file->path)
        ) {
            return null;
        }

        return $file;
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
     * @param  array<string, mixed>  $dataSnapshot
     * @return array{header: array{enabled: bool, content: ?string, banner_path: ?string}, footer: array{enabled: bool, content: ?string, banner_path: ?string, page_numbers_enabled: bool}}|null
     */
    private function renderedLayout(?DocumentLayoutTemplate $layout, array $dataSnapshot): ?array
    {
        if (! $layout instanceof DocumentLayoutTemplate) {
            return null;
        }

        return [
            'header' => [
                'enabled' => $layout->header_enabled,
                'content' => $layout->header_enabled
                    ? $this->templateRenderer->render($layout->header_content, $dataSnapshot, '')
                    : null,
                'banner_path' => $layout->header_enabled ? $layout->header_banner_path : null,
            ],
            'footer' => [
                'enabled' => $layout->footer_enabled,
                'content' => $layout->footer_enabled
                    ? $this->templateRenderer->render($layout->footer_content, $dataSnapshot, '')
                    : null,
                'banner_path' => $layout->footer_enabled ? $layout->footer_banner_path : null,
                'page_numbers_enabled' => $layout->footer_enabled && $layout->page_numbers_enabled,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function layoutSnapshot(?DocumentLayoutTemplate $layout): ?array
    {
        if (! $layout instanceof DocumentLayoutTemplate) {
            return null;
        }

        return [
            'id' => $layout->id,
            'owner_type' => DocumentLayoutTemplate::ownerTypeLabel($layout->owner_type),
            'owner_id' => $layout->owner_id,
            'name' => $layout->name,
            'document_type' => $layout->document_type,
            'locale' => $layout->locale,
            'version' => $layout->version,
            'status' => $layout->status,
            'header_enabled' => $layout->header_enabled,
            'footer_enabled' => $layout->footer_enabled,
            'page_numbers_enabled' => $layout->page_numbers_enabled,
            'header_content' => $layout->header_content,
            'footer_content' => $layout->footer_content,
            'header_banner_path' => $layout->header_banner_path,
            'footer_banner_path' => $layout->footer_banner_path,
            'placeholders' => $layout->placeholders,
            'metadata' => $layout->metadata,
        ];
    }
}
