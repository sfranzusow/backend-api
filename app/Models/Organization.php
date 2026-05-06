<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'email',
        'phone_number',
        'website',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
