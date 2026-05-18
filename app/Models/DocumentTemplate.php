<?php

namespace App\Models;

use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'document_type',
        'template_type',
        'locale',
        'version',
        'status',
        'content',
        'placeholders',
        'metadata',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'placeholders' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_ARCHIVED,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }
}
