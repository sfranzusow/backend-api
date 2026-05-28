<?php

namespace App\Models;

use App\Policies\RentalAgreementPolicy;
use Database\Factories\RentalAgreementFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[UsePolicy(RentalAgreementPolicy::class)]
class RentalAgreement extends Model
{
    /** @use HasFactory<RentalAgreementFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_ENDED = 'ended';

    private const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_DRAFT, self::STATUS_ACTIVE],
        self::STATUS_ACTIVE => [self::STATUS_ACTIVE, self::STATUS_TERMINATED, self::STATUS_ENDED],
        self::STATUS_TERMINATED => [self::STATUS_TERMINATED],
        self::STATUS_ENDED => [self::STATUS_ENDED],
    ];

    protected $fillable = [
        'property_id',
        'landlord_id',
        'tenant_id',
        'bank_account_id',
        'date_from',
        'date_to',
        'rent_cold',
        'rent_warm',
        'service_charges',
        'deposit',
        'currency',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'rent_cold' => 'decimal:2',
            'rent_warm' => 'decimal:2',
            'service_charges' => 'decimal:2',
            'deposit' => 'decimal:2',
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
            self::STATUS_TERMINATED,
            self::STATUS_ENDED,
        ];
    }

    public function canTransitionToStatus(string $status): bool
    {
        return in_array($status, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable')
            ->orderBy('due_date')
            ->orderBy('id');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable')
            ->orderBy('due_at')
            ->orderBy('id');
    }
}
