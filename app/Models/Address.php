<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
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