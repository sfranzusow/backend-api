<?php

namespace App\Models;

use Database\Factories\DocumentFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFile extends Model
{
    /** @use HasFactory<DocumentFileFactory> */
    use HasFactory;

    public const TYPE_GENERATED_PDF = 'generated_pdf';

    public const TYPE_SIGNED_UPLOAD = 'signed_upload';

    public const TYPE_ATTACHMENT = 'attachment';

    protected $fillable = [
        'document_version_id',
        'file_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'metadata',
        'uploaded_by_id',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function fileTypes(): array
    {
        return [
            self::TYPE_GENERATED_PDF,
            self::TYPE_SIGNED_UPLOAD,
            self::TYPE_ATTACHMENT,
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
