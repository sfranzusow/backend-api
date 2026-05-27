<?php

namespace App\Models;

use App\Policies\OrganizationPolicy;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[UsePolicy(OrganizationPolicy::class)]
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

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function documentLayoutTemplates(): MorphMany
    {
        return $this->morphMany(DocumentLayoutTemplate::class, 'owner');
    }
}
