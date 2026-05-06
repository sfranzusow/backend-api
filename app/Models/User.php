<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'phone_number',
    'address_street',
    'address_house_number',
    'address_zip_code',
    'address_city',
    'address_country',
    'organization_id',
])]
#[Hidden(['password', 'remember_token'])]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function scopeFilter($query, array $filters): Builder
    {
        return $query
            ->when($filters['name'] ?? null, function ($query, $name) {
                $query->where('name', 'like', '%'.$name.'%');
            })
            ->when($filters['email'] ?? null, function ($query, $email) {
                $query->where('email', 'like', '%'.$email.'%');
            })
            ->when($filters['phone_number'] ?? null, function ($query, $phoneNumber) {
                $query->where('phone_number', 'like', '%'.$phoneNumber.'%');
            })
            ->when($filters['organization_id'] ?? null, function ($query, $organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->when($filters['role'] ?? null, function ($query, $role) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $role));
            });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)
            ->withPivot(['role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function landlordProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)
            ->wherePivot('role', 'landlord')
            ->withPivot(['role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function tenantProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)
            ->wherePivot('role', 'tenant')
            ->withPivot(['role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function managedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)
            ->wherePivot('role', 'manager')
            ->withPivot(['role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function landlordRentalAgreements(): HasMany
    {
        return $this->hasMany(RentalAgreement::class, 'landlord_id');
    }

    public function tenantRentalAgreements(): HasMany
    {
        return $this->hasMany(RentalAgreement::class, 'tenant_id');
    }
}
