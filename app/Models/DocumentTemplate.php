<?php

namespace App\Models;

use App\Policies\DocumentTemplatePolicy;
use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(DocumentTemplatePolicy::class)]
class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory;

    public const TYPE_RENTAL_AGREEMENT_CONTRACT = 'rental_agreement_contract';

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

    /**
     * @return list<string>
     */
    public static function documentTypes(): array
    {
        return [
            self::TYPE_RENTAL_AGREEMENT_CONTRACT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedPlaceholdersFor(string $documentType): array
    {
        return match ($documentType) {
            self::TYPE_RENTAL_AGREEMENT_CONTRACT => [
                'document.id',
                'document.title',
                'document.document_type',
                'rental_agreement.id',
                'rental_agreement.date_from',
                'rental_agreement.date_to',
                'rental_agreement.rent_cold',
                'rental_agreement.rent_warm',
                'rental_agreement.service_charges',
                'rental_agreement.deposit',
                'rental_agreement.currency',
                'rental_agreement.status',
                'rental_agreement.notes',
                'bank_account.id',
                'bank_account.account_holder',
                'bank_account.iban',
                'bank_account.bic',
                'bank_account.bank_name',
                'property.id',
                'property.unit_number',
                'property.type',
                'property.area_living',
                'property.rooms',
                'property.floor',
                'property.address',
                'property.address_details.street',
                'property.address_details.house_number',
                'property.address_details.zip_code',
                'property.address_details.city',
                'property.address_details.country',
                'landlord.id',
                'landlord.name',
                'landlord.email',
                'landlord.phone_number',
                'landlord.address_street',
                'landlord.address_house_number',
                'landlord.address_zip_code',
                'landlord.address_city',
                'landlord.address_country',
                'tenant.id',
                'tenant.name',
                'tenant.email',
                'tenant.phone_number',
                'tenant.address_street',
                'tenant.address_house_number',
                'tenant.address_zip_code',
                'tenant.address_city',
                'tenant.address_country',
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function extractPlaceholders(?string $content): array
    {
        if ($content === null || $content === '') {
            return [];
        }

        preg_match_all('/{{\s*([A-Za-z0-9_.]+)\s*}}/', $content, $matches);

        $placeholders = array_values(array_unique($matches[1] ?? []));
        sort($placeholders);

        return $placeholders;
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
