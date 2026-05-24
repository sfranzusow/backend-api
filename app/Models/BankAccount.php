<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Policies\BankAccountPolicy;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(BankAccountPolicy::class)]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'account_holder',
        'iban',
        'bic',
        'bank_name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $authUser): Builder
    {
        if ($authUser->hasRole(RoleName::Admin->value)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($authUser): void {
            $query->where('user_id', $authUser->id);

            if ($authUser->organization_id !== null) {
                $query->orWhere('organization_id', $authUser->organization_id);
            }
        });
    }

    public static function existsForLandlord(int $bankAccountId, int $landlordId): bool
    {
        return self::queryForLandlord($landlordId)
            ->whereKey($bankAccountId)
            ->exists();
    }

    public static function queryForLandlord(int $landlordId): Builder
    {
        $organizationId = User::query()
            ->whereKey($landlordId)
            ->value('organization_id');

        return self::query()->where(function (Builder $query) use ($landlordId, $organizationId): void {
            $query->where('user_id', $landlordId);

            if ($organizationId !== null) {
                $query->orWhere('organization_id', $organizationId);
            }
        });
    }

    public function isVisibleTo(User $authUser): bool
    {
        if ($authUser->hasRole(RoleName::Admin->value)) {
            return true;
        }

        return $this->user_id === $authUser->id
            || (
                $this->organization_id !== null
                && $this->organization_id === $authUser->organization_id
            );
    }

    public function clearOtherDefaultsForSameOwner(): void
    {
        self::query()
            ->whereKeyNot($this->id)
            ->where(function (Builder $query): void {
                if ($this->user_id !== null) {
                    $query->where('user_id', $this->user_id);

                    return;
                }

                $query->where('organization_id', $this->organization_id);
            })
            ->update([
                'is_default' => false,
            ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rentalAgreements(): HasMany
    {
        return $this->hasMany(RentalAgreement::class);
    }
}
