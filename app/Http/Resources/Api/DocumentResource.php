<?php

namespace App\Http\Resources\Api;

use App\Enums\RoleName;
use App\Models\Document as DocumentModel;
use App\Models\DocumentFile;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Reminder;
use App\Models\RentalAgreement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
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
            'snapshot_status' => $this->snapshotStatus(),
            'reminders' => ReminderResource::collection($this->whenLoaded('reminders')),
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
            'create_reminder' => $authUser->can('createForRemindable', [Reminder::class, $document]),
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

    /**
     * @return array{state: string, is_outdated: bool, reason: string|null, generated_at: string|null, source_updated_at: string|null}
     */
    private function snapshotStatus(): array
    {
        $latestVersion = $this->latestVersion();
        $generatedAt = $latestVersion?->generated_at;

        if (
            ! $latestVersion instanceof DocumentVersion
            || ! $generatedAt instanceof CarbonInterface
            || $latestVersion->status === DocumentVersion::STATUS_VOID
        ) {
            return [
                'state' => 'not_generated',
                'is_outdated' => false,
                'reason' => null,
                'generated_at' => null,
                'source_updated_at' => null,
            ];
        }

        $sourceUpdatedAt = $this->sourceUpdatedAt();

        if (! $sourceUpdatedAt instanceof CarbonInterface) {
            return [
                'state' => 'unknown',
                'is_outdated' => false,
                'reason' => null,
                'generated_at' => $generatedAt->toISOString(),
                'source_updated_at' => null,
            ];
        }

        $isOutdated = $sourceUpdatedAt->greaterThan($generatedAt);

        return [
            'state' => $isOutdated ? 'outdated' : 'current',
            'is_outdated' => $isOutdated,
            'reason' => $isOutdated ? 'source_data_changed_after_generation' : null,
            'generated_at' => $generatedAt->toISOString(),
            'source_updated_at' => $sourceUpdatedAt->toISOString(),
        ];
    }

    private function sourceUpdatedAt(): ?CarbonInterface
    {
        $document = $this->resource;

        if (! $document instanceof DocumentModel || ! $document->relationLoaded('documentable')) {
            return null;
        }

        $documentable = $document->getRelation('documentable');

        if (! $documentable instanceof RentalAgreement) {
            return null;
        }

        $timestamps = [];
        $this->addModelTimestamp($timestamps, $documentable);

        $property = $documentable->relationLoaded('property')
            ? $documentable->getRelation('property')
            : null;
        $this->addModelTimestamp($timestamps, $property);

        if ($property instanceof Model && $property->relationLoaded('address')) {
            $this->addModelTimestamp($timestamps, $property->getRelation('address'));
        }

        $landlord = $documentable->relationLoaded('landlord')
            ? $documentable->getRelation('landlord')
            : null;
        $this->addModelTimestamp($timestamps, $landlord);

        if ($landlord instanceof Model && $landlord->relationLoaded('organization')) {
            $this->addModelTimestamp($timestamps, $landlord->getRelation('organization'));
        }

        $this->addModelTimestamp(
            $timestamps,
            $documentable->relationLoaded('tenant') ? $documentable->getRelation('tenant') : null,
        );
        $this->addModelTimestamp(
            $timestamps,
            $documentable->relationLoaded('bankAccount') ? $documentable->getRelation('bankAccount') : null,
        );

        return $this->latestTimestamp($timestamps);
    }

    /**
     * @param  list<CarbonInterface>  $timestamps
     */
    private function addModelTimestamp(array &$timestamps, mixed $model): void
    {
        if ($model instanceof Model && $model->updated_at instanceof CarbonInterface) {
            $timestamps[] = $model->updated_at;
        }
    }

    /**
     * @param  list<CarbonInterface>  $timestamps
     */
    private function latestTimestamp(array $timestamps): ?CarbonInterface
    {
        $latestTimestamp = null;

        foreach ($timestamps as $timestamp) {
            if (! $latestTimestamp instanceof CarbonInterface || $timestamp->greaterThan($latestTimestamp)) {
                $latestTimestamp = $timestamp;
            }
        }

        return $latestTimestamp;
    }
}
