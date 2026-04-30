<?php

namespace App\Models;

use Database\Factories\RentalAgreementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalAgreement extends Model
{
    /** @use HasFactory<RentalAgreementFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id',
        'landlord_id',
        'tenant_id',
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
}
