<?php

namespace App\Models;

use App\Policies\PaymentPolicy;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[UsePolicy(PaymentPolicy::class)]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const TYPE_RENT = 'rent';

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_DEPOSIT_REFUND = 'deposit_refund';

    public const TYPE_SERVICE_CHARGE = 'service_charge';

    public const TYPE_OTHER = 'other';

    public const DIRECTION_INCOMING = 'incoming';

    public const DIRECTION_OUTGOING = 'outgoing';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'payable_type',
        'payable_id',
        'type',
        'direction',
        'status',
        'amount',
        'currency',
        'due_date',
        'paid_at',
        'payer_id',
        'payee_id',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_RENT,
            self::TYPE_DEPOSIT,
            self::TYPE_DEPOSIT_REFUND,
            self::TYPE_SERVICE_CHARGE,
            self::TYPE_OTHER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function directions(): array
    {
        return [
            self::DIRECTION_INCOMING,
            self::DIRECTION_OUTGOING,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PLANNED,
            self::STATUS_PENDING,
            self::STATUS_PAID,
            self::STATUS_OVERDUE,
            self::STATUS_CANCELLED,
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_id');
    }
}
