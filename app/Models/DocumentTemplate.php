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
        return array_map(
            static fn (array $placeholder): string => $placeholder['path'],
            self::placeholderDefinitionsFor($documentType)
        );
    }

    /**
     * @return list<array{path: string, label: string, group: string, type: string, nullable: bool, example: string}>
     */
    public static function placeholderDefinitionsFor(string $documentType): array
    {
        return match ($documentType) {
            self::TYPE_RENTAL_AGREEMENT_CONTRACT => [
                self::placeholder('document.id', 'Dokument-ID', 'Dokument', 'integer', false),
                self::placeholder('document.title', 'Dokumenttitel', 'Dokument', 'string', false),
                self::placeholder('document.document_type', 'Dokumenttyp', 'Dokument', 'string', false),
                self::placeholder('document.version_number', 'Dokumentversion', 'Dokument', 'integer', false),
                self::placeholder('document.generated_at', 'Erzeugt am', 'Dokument', 'datetime', false),
                self::placeholder('rental_agreement.id', 'Mietvertrags-ID', 'Mietvertrag', 'integer', false),
                self::placeholder('rental_agreement.date_from', 'Mietbeginn', 'Mietvertrag', 'date', false),
                self::placeholder('rental_agreement.date_to', 'Mietende', 'Mietvertrag', 'date'),
                self::placeholder('rental_agreement.rent_cold', 'Nettokaltmiete', 'Mietvertrag', 'decimal', false),
                self::placeholder('rental_agreement.rent_warm', 'Warmmiete', 'Mietvertrag', 'decimal'),
                self::placeholder('rental_agreement.service_charges', 'Betriebskostenvorauszahlung', 'Mietvertrag', 'decimal'),
                self::placeholder('rental_agreement.deposit', 'Kaution', 'Mietvertrag', 'decimal'),
                self::placeholder('rental_agreement.currency', 'Währung', 'Mietvertrag', 'string', false),
                self::placeholder('rental_agreement.status', 'Status', 'Mietvertrag', 'string', false),
                self::placeholder('rental_agreement.lease_subject_description', 'Weitere Beschreibung des Mietgegenstands', 'Mietvertrag'),
                self::placeholder('rental_agreement.additional_spaces', 'Mitvermietete Nebenräume / Stellplätze', 'Mietvertrag'),
                self::placeholder('rental_agreement.shared_facilities', 'Mitbenutzungsrechte an Gemeinschaftsflächen', 'Mietvertrag'),
                self::placeholder('rental_agreement.fixed_term_reason', 'Befristungsgrund', 'Mietvertrag'),
                self::placeholder('rental_agreement.handover_due_at', 'Übergabe spätestens am', 'Mietvertrag', 'date'),
                self::placeholder('rental_agreement.operating_costs_allocation_key', 'Umlageschlüssel Betriebskosten', 'Mietvertrag'),
                self::placeholder('rental_agreement.renovation_condition', 'Renovierungszustand bei Übergabe', 'Mietvertrag'),
                self::placeholder('rental_agreement.renovation_condition_notes', 'Details zum Renovierungszustand', 'Mietvertrag'),
                self::placeholder('rental_agreement.cosmetic_repairs_agreement', 'Individuelle Vereinbarung zu Schönheitsreparaturen', 'Mietvertrag'),
                self::placeholder('rental_agreement.small_repairs_single_limit', 'Kleinreparaturgrenze je Einzelfall', 'Mietvertrag', 'decimal'),
                self::placeholder('rental_agreement.small_repairs_annual_limit', 'Kleinreparaturgrenze pro Kalenderjahr', 'Mietvertrag', 'decimal'),
                self::placeholder('rental_agreement.handover_protocol_attached', 'Übergabeprotokoll beigefügt', 'Mietvertrag', 'boolean', false),
                self::placeholder('rental_agreement.house_rules_attached', 'Hausordnung beigefügt', 'Mietvertrag', 'boolean', false),
                self::placeholder('rental_agreement.operating_costs_overview_attached', 'Betriebskostenübersicht beigefügt', 'Mietvertrag', 'boolean', false),
                self::placeholder('rental_agreement.energy_certificate_attached', 'Energieausweis beigefügt', 'Mietvertrag', 'boolean', false),
                self::placeholder('rental_agreement.self_disclosure_attached', 'Selbstauskunft / besondere Vereinbarungen beigefügt', 'Mietvertrag', 'boolean', false),
                self::placeholder('rental_agreement.other_attachments', 'Sonstige Anlagen', 'Mietvertrag'),
                self::placeholder('rental_agreement.individual_agreements', 'Weitere individuelle Vereinbarungen', 'Mietvertrag'),
                self::placeholder('rental_agreement.notes', 'Interne Notizen', 'Mietvertrag'),
                self::placeholder('bank_account.id', 'Bankkonto-ID', 'Bankkonto', 'integer'),
                self::placeholder('bank_account.account_holder', 'Kontoinhaber', 'Bankkonto'),
                self::placeholder('bank_account.iban', 'IBAN', 'Bankkonto'),
                self::placeholder('bank_account.bic', 'BIC', 'Bankkonto'),
                self::placeholder('bank_account.bank_name', 'Bankname', 'Bankkonto'),
                self::placeholder('organization.id', 'Organisations-ID', 'Organisation', 'integer'),
                self::placeholder('organization.name', 'Organisationsname', 'Organisation'),
                self::placeholder('organization.type', 'Organisationstyp', 'Organisation'),
                self::placeholder('organization.email', 'Organisations-E-Mail', 'Organisation'),
                self::placeholder('organization.phone_number', 'Organisationstelefon', 'Organisation'),
                self::placeholder('organization.website', 'Organisationswebsite', 'Organisation'),
                self::placeholder('property.id', 'Objekt-ID', 'Objekt', 'integer', false),
                self::placeholder('property.unit_number', 'Einheit / Wohnungsnummer', 'Objekt'),
                self::placeholder('property.type', 'Objektart', 'Objekt', 'string', false),
                self::placeholder('property.area_living', 'Wohnfläche', 'Objekt', 'decimal', false),
                self::placeholder('property.rooms', 'Zimmer', 'Objekt', 'integer', false),
                self::placeholder('property.floor', 'Etage', 'Objekt', 'integer'),
                self::placeholder('property.address', 'Objektadresse', 'Objekt', 'string', false),
                self::placeholder('property.address_details.street', 'Straße', 'Objektadresse', 'string', false),
                self::placeholder('property.address_details.house_number', 'Hausnummer', 'Objektadresse', 'string', false),
                self::placeholder('property.address_details.zip_code', 'Postleitzahl', 'Objektadresse', 'string', false),
                self::placeholder('property.address_details.city', 'Ort', 'Objektadresse', 'string', false),
                self::placeholder('property.address_details.country', 'Land', 'Objektadresse', 'string', false),
                self::placeholder('landlord.id', 'Vermieter-ID', 'Vermieter', 'integer', false),
                self::placeholder('landlord.name', 'Name des Vermieters', 'Vermieter', 'string', false),
                self::placeholder('landlord.email', 'E-Mail des Vermieters', 'Vermieter', 'string', false),
                self::placeholder('landlord.phone_number', 'Telefon des Vermieters', 'Vermieter'),
                self::placeholder('landlord.address_street', 'Straße des Vermieters', 'Vermieter'),
                self::placeholder('landlord.address_house_number', 'Hausnummer des Vermieters', 'Vermieter'),
                self::placeholder('landlord.address_zip_code', 'Postleitzahl des Vermieters', 'Vermieter'),
                self::placeholder('landlord.address_city', 'Ort des Vermieters', 'Vermieter'),
                self::placeholder('landlord.address_country', 'Land des Vermieters', 'Vermieter'),
                self::placeholder('tenant.id', 'Mieter-ID', 'Mieter', 'integer', false),
                self::placeholder('tenant.name', 'Name des Mieters', 'Mieter', 'string', false),
                self::placeholder('tenant.email', 'E-Mail des Mieters', 'Mieter', 'string', false),
                self::placeholder('tenant.phone_number', 'Telefon des Mieters', 'Mieter'),
                self::placeholder('tenant.address_street', 'Straße des Mieters', 'Mieter'),
                self::placeholder('tenant.address_house_number', 'Hausnummer des Mieters', 'Mieter'),
                self::placeholder('tenant.address_zip_code', 'Postleitzahl des Mieters', 'Mieter'),
                self::placeholder('tenant.address_city', 'Ort des Mieters', 'Mieter'),
                self::placeholder('tenant.address_country', 'Land des Mieters', 'Mieter'),
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

    /**
     * @return array{path: string, label: string, group: string, type: string, nullable: bool, example: string}
     */
    private static function placeholder(
        string $path,
        string $label,
        string $group,
        string $type = 'string',
        bool $nullable = true
    ): array {
        return [
            'path' => $path,
            'label' => $label,
            'group' => $group,
            'type' => $type,
            'nullable' => $nullable,
            'example' => '{{ '.$path.' }}',
        ];
    }
}
