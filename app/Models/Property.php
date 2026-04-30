<?php

namespace App\Models;

use App\Policies\PropertyPolicy;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(PropertyPolicy::class)]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected $fillable = [
        'address_id',
        'unit_number',
        'type',
        'area_living',
        'rooms',
        'floor',
        'build_year',
        'energy_class',
        'price',
        'features',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'area_living' => 'decimal:2',
            'price' => 'decimal:2',
            'features' => 'array',
            'build_year' => 'integer',
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function landlords(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', 'landlord')
            ->withPivot(['role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', 'tenant')
            ->withPivot(['role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', 'manager')
            ->withPivot(['role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function rentalAgreements(): HasMany
    {
        return $this->hasMany(RentalAgreement::class);
    }
}
