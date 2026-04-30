<?php

namespace App\Models;

use App\Policies\AddressPolicy;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(AddressPolicy::class)]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected $fillable = [
        'street',
        'house_number',
        'zip_code',
        'city',
        'country',
        'latitude',
        'longitude',
    ];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
