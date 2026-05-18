<?php

namespace App\Models;

use Database\Factories\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentVersion extends Model
{
    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_SHARED = 'shared';

    public const STATUS_SIGNED_UPLOADED = 'signed_uploaded';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'document_id',
        'document_template_id',
        'version_number',
        'status',
        'title',
        'content_snapshot',
        'template_snapshot',
        'data_snapshot',
        'metadata',
        'generated_by_id',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'template_snapshot' => 'array',
            'data_snapshot' => 'array',
            'metadata' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_GENERATED,
            self::STATUS_SHARED,
            self::STATUS_SIGNED_UPLOADED,
            self::STATUS_VOID,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }
}
