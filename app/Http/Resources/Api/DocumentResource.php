<?php

namespace App\Http\Resources\Api;

use App\Enums\RoleName;
use App\Models\Document as DocumentModel;
use App\Models\DocumentFile;
use App\Models\DocumentReminder;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isTenantView = $this->isTenantView($request);

        return [
            'id' => $this->id,
            'documentable_type' => class_basename($this->documentable_type),
            'documentable_id' => $this->documentable_id,
            $this->mergeWhen(! $isTenantView, [
                'document_template_id' => $this->document_template_id,
            ]),
            'document_type' => $this->document_type,
            'status' => $this->status,
            'title' => $this->title,
            $this->mergeWhen(! $isTenantView, [
                'metadata' => $this->metadata,
                'template' => DocumentTemplateResource::make($this->whenLoaded('template')),
            ]),
            'latest_version' => DocumentVersionResource::make($this->whenLoaded('latestVersion')),
            'reminders' => DocumentReminderResource::collection($this->whenLoaded('reminders')),
            $this->mergeWhen(! $isTenantView, [
                'created_by_id' => $this->created_by_id,
                'creator' => UserResource::make($this->whenLoaded('creator')),
            ]),
            'actions' => $this->actions($request),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function isTenantView(Request $request): bool
    {
        $authUser = $request->user();

        return $authUser?->hasRole(RoleName::Tenant->value) === true
            && ! $authUser->hasRole(RoleName::Landlord->value)
            && ! $authUser->hasRole(RoleName::Admin->value);
    }

    /**
     * @return array<string, bool>
     */
    private function actions(Request $request): array
    {
        $authUser = $request->user();
        $document = $this->resource;

        if (! $authUser instanceof User || ! $document instanceof DocumentModel) {
            return $this->emptyActions();
        }

        $latestVersion = $this->latestVersion();

        return [
            'generate' => $authUser->can('generate', $document)
                && $this->isRentalAgreementDocument($document)
                && $document->canTransitionToStatus(DocumentModel::STATUS_GENERATED)
                && $this->hasActiveTemplate($document),
            'share' => $authUser->can('share', $document)
                && $document->status === DocumentModel::STATUS_GENERATED
                && $latestVersion instanceof DocumentVersion
                && $latestVersion->status === DocumentVersion::STATUS_GENERATED,
            'void' => $authUser->can('voidDocument', $document)
                && $document->canTransitionToStatus(DocumentModel::STATUS_VOID)
                && $document->status !== DocumentModel::STATUS_VOID,
            'download' => $authUser->can('download', $document)
                && $this->hasVersionFile($latestVersion, DocumentFile::TYPE_GENERATED_PDF),
            'upload_signed' => $authUser->can('uploadSigned', $document)
                && $document->canTransitionToStatus(DocumentModel::STATUS_SIGNED_UPLOADED)
                && $latestVersion instanceof DocumentVersion
                && $latestVersion->canTransitionToStatus(DocumentVersion::STATUS_SIGNED_UPLOADED),
            'download_signed' => $authUser->can('downloadSigned', $document)
                && $this->hasVersionFile($latestVersion, DocumentFile::TYPE_SIGNED_UPLOAD),
            'create_reminder' => $authUser->can('createForDocument', [DocumentReminder::class, $document]),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function emptyActions(): array
    {
        return [
            'generate' => false,
            'share' => false,
            'void' => false,
            'download' => false,
            'upload_signed' => false,
            'download_signed' => false,
            'create_reminder' => false,
        ];
    }

    private function latestVersion(): ?DocumentVersion
    {
        $document = $this->resource;

        if (! $document instanceof DocumentModel || ! $document->relationLoaded('latestVersion')) {
            return null;
        }

        $latestVersion = $document->getRelation('latestVersion');

        return $latestVersion instanceof DocumentVersion ? $latestVersion : null;
    }

    private function isRentalAgreementDocument(DocumentModel $document): bool
    {
        return $document->documentable_type === RentalAgreement::class;
    }

    private function hasActiveTemplate(DocumentModel $document): bool
    {
        $template = $document->relationLoaded('template')
            ? $document->getRelation('template')
            : null;

        if (
            $template instanceof DocumentTemplate
            && $template->status === DocumentTemplate::STATUS_ACTIVE
            && $template->document_type === $document->document_type
        ) {
            return true;
        }

        if ($document->document_template_id !== null) {
            return DocumentTemplate::query()
                ->whereKey($document->document_template_id)
                ->where('document_type', $document->document_type)
                ->where('status', DocumentTemplate::STATUS_ACTIVE)
                ->exists();
        }

        return DocumentTemplate::query()
            ->where('document_type', $document->document_type)
            ->where('status', DocumentTemplate::STATUS_ACTIVE)
            ->exists();
    }

    private function hasVersionFile(?DocumentVersion $latestVersion, string $fileType): bool
    {
        if (
            ! $latestVersion instanceof DocumentVersion
            || $latestVersion->status === DocumentVersion::STATUS_VOID
            || ! $latestVersion->relationLoaded('files')
        ) {
            return false;
        }

        return $latestVersion->files->contains('file_type', $fileType);
    }
}
