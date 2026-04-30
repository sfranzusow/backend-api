<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
