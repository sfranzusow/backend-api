<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Policies\ReminderPolicy;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[UsePolicy(ReminderPolicy::class)]
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const DISPLAY_STATUS_OPEN = 'open';

    public const DISPLAY_STATUS_REMINDER_DUE = 'reminder_due';

    public const DISPLAY_STATUS_OVERDUE = 'overdue';

    public const REMINDABLE_TYPE_DOCUMENT = 'Document';

    public const REMINDABLE_TYPE_RENTAL_AGREEMENT = 'RentalAgreement';

    public const REMINDABLE_TYPE_PAYMENT = 'Payment';

    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'title',
        'notes',
        'due_at',
        'remind_at',
        'status',
        'metadata',
        'assigned_to_id',
        'created_by_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_DONE) {
            return self::STATUS_DONE;
        }

        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        $now = now();

        if ($this->due_at?->lessThanOrEqualTo($now) === true) {
            return self::DISPLAY_STATUS_OVERDUE;
        }

        if ($this->remind_at?->lessThanOrEqualTo($now) === true) {
            return self::DISPLAY_STATUS_REMINDER_DUE;
        }

        return self::DISPLAY_STATUS_OPEN;
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_DONE,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function displayStatuses(): array
    {
        return [
            self::DISPLAY_STATUS_OPEN,
            self::DISPLAY_STATUS_REMINDER_DUE,
            self::DISPLAY_STATUS_OVERDUE,
            self::STATUS_DONE,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, class-string<Model>>
     */
    public static function remindableTypes(): array
    {
        return [
            self::REMINDABLE_TYPE_DOCUMENT => Document::class,
            self::REMINDABLE_TYPE_RENTAL_AGREEMENT => RentalAgreement::class,
            self::REMINDABLE_TYPE_PAYMENT => Payment::class,
        ];
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function remindableTypeClasses(): array
    {
        return array_values(self::remindableTypes());
    }

    public function scopeAssignedVisibleTo(Builder $query, User $authUser): Builder
    {
        return $query
            ->where('assigned_to_id', $authUser->id)
            ->where(function (Builder $query) use ($authUser): void {
                self::applyVisibleRemindableConstraints($query, $authUser);
            });
    }

    public function scopeWithDisplayStatus(Builder $query, string $displayStatus): Builder
    {
        $now = now();

        return match ($displayStatus) {
            self::STATUS_DONE => $query->where('status', self::STATUS_DONE),
            self::STATUS_CANCELLED => $query->where('status', self::STATUS_CANCELLED),
            self::DISPLAY_STATUS_OVERDUE => $query
                ->where('status', self::STATUS_PENDING)
                ->whereNotNull('due_at')
                ->where('due_at', '<=', $now),
            self::DISPLAY_STATUS_REMINDER_DUE => $query
                ->where('status', self::STATUS_PENDING)
                ->whereNotNull('remind_at')
                ->where('remind_at', '<=', $now)
                ->where(function (Builder $query) use ($now): void {
                    $query
                        ->whereNull('due_at')
                        ->orWhere('due_at', '>', $now);
                }),
            default => $query
                ->where('status', self::STATUS_PENDING)
                ->where(function (Builder $query) use ($now): void {
                    $query
                        ->whereNull('due_at')
                        ->orWhere('due_at', '>', $now);
                })
                ->where(function (Builder $query) use ($now): void {
                    $query
                        ->whereNull('remind_at')
                        ->orWhere('remind_at', '>', $now);
                }),
        };
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    private static function applyVisibleRemindableConstraints(Builder $query, User $authUser): void
    {
        if ($authUser->hasRole(RoleName::Admin->value)) {
            $query->whereIn('remindable_type', self::remindableTypeClasses());

            return;
        }

        $hasConstraint = false;

        if ($authUser->hasRole(RoleName::Landlord->value)) {
            self::addRoleVisibleRemindables($query, $hasConstraint, function (Builder $query) use ($authUser): void {
                self::addLandlordVisibleRemindables($query, $authUser);
            });
            $hasConstraint = true;
        }

        if ($authUser->hasRole(RoleName::Tenant->value)) {
            self::addRoleVisibleRemindables($query, $hasConstraint, function (Builder $query) use ($authUser): void {
                self::addTenantVisibleRemindables($query, $authUser);
            });
            $hasConstraint = true;
        }

        if (! $hasConstraint) {
            $query->whereRaw('1 = 0');
        }
    }

    private static function addRoleVisibleRemindables(Builder $query, bool $orWhere, callable $callback): void
    {
        if ($orWhere) {
            $query->orWhere($callback);

            return;
        }

        $query->where($callback);
    }

    private static function addLandlordVisibleRemindables(Builder $query, User $authUser): void
    {
        $query
            ->where(function (Builder $query) use ($authUser): void {
                $query
                    ->where('remindable_type', RentalAgreement::class)
                    ->whereIn('remindable_id', RentalAgreement::query()
                        ->select('id')
                        ->where('landlord_id', $authUser->id));
            })
            ->orWhere(function (Builder $query) use ($authUser): void {
                $query
                    ->where('remindable_type', Document::class)
                    ->whereIn('remindable_id', Document::query()
                        ->select('id')
                        ->where('documentable_type', RentalAgreement::class)
                        ->whereIn('documentable_id', RentalAgreement::query()
                            ->select('id')
                            ->where('landlord_id', $authUser->id)));
            })
            ->orWhere(function (Builder $query) use ($authUser): void {
                $query
                    ->where('remindable_type', Payment::class)
                    ->whereIn('remindable_id', Payment::query()
                        ->select('id')
                        ->where('payable_type', RentalAgreement::class)
                        ->whereIn('payable_id', RentalAgreement::query()
                            ->select('id')
                            ->where('landlord_id', $authUser->id)));
            });
    }

    private static function addTenantVisibleRemindables(Builder $query, User $authUser): void
    {
        $query
            ->where(function (Builder $query) use ($authUser): void {
                $query
                    ->where('remindable_type', RentalAgreement::class)
                    ->whereIn('remindable_id', RentalAgreement::query()
                        ->select('id')
                        ->where('tenant_id', $authUser->id));
            })
            ->orWhere(function (Builder $query) use ($authUser): void {
                $query
                    ->where('remindable_type', Document::class)
                    ->whereIn('remindable_id', Document::query()
                        ->select('id')
                        ->where('documentable_type', RentalAgreement::class)
                        ->whereIn('documentable_id', RentalAgreement::query()
                            ->select('id')
                            ->where('tenant_id', $authUser->id))
                        ->whereIn('status', Document::tenantVisibleStatuses()));
            })
            ->orWhere(function (Builder $query) use ($authUser): void {
                $query
                    ->where('remindable_type', Payment::class)
                    ->whereIn('remindable_id', Payment::query()
                        ->select('id')
                        ->where('payable_type', RentalAgreement::class)
                        ->whereIn('payable_id', RentalAgreement::query()
                            ->select('id')
                            ->where('tenant_id', $authUser->id)));
            });
    }
}
