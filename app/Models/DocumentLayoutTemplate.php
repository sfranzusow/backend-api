<?php

namespace App\Models;

use App\Policies\DocumentLayoutTemplatePolicy;
use Database\Factories\DocumentLayoutTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

#[UsePolicy(DocumentLayoutTemplatePolicy::class)]
class DocumentLayoutTemplate extends Model
{
    /** @use HasFactory<DocumentLayoutTemplateFactory> */
    use HasFactory;

    public const OWNER_TYPE_ORGANIZATION = 'organization';

    public const OWNER_TYPE_USER = 'user';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'document_type',
        'locale',
        'version',
        'status',
        'header_enabled',
        'footer_enabled',
        'page_numbers_enabled',
        'header_content',
        'footer_content',
        'header_banner_path',
        'footer_banner_path',
        'placeholders',
        'metadata',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'header_enabled' => 'boolean',
            'footer_enabled' => 'boolean',
            'page_numbers_enabled' => 'boolean',
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

    /**
     * @return list<string>
     */
    public static function ownerTypes(): array
    {
        return [
            self::OWNER_TYPE_ORGANIZATION,
            self::OWNER_TYPE_USER,
        ];
    }

    public static function ownerClassFor(string $ownerType): string
    {
        return match ($ownerType) {
            self::OWNER_TYPE_ORGANIZATION, Organization::class => Organization::class,
            self::OWNER_TYPE_USER, User::class => User::class,
            default => throw new InvalidArgumentException('Unsupported document layout owner type.'),
        };
    }

    public static function ownerTypeLabel(?string $ownerClass): ?string
    {
        return match ($ownerClass) {
            Organization::class => self::OWNER_TYPE_ORGANIZATION,
            User::class => self::OWNER_TYPE_USER,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function extractLayoutPlaceholders(?string $headerContent, ?string $footerContent): array
    {
        $placeholders = [
            ...DocumentTemplate::extractPlaceholders($headerContent),
            ...DocumentTemplate::extractPlaceholders($footerContent),
        ];

        $placeholders = array_values(array_unique($placeholders));
        sort($placeholders);

        return $placeholders;
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }
}
