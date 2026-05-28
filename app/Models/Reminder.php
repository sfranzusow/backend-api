<?php

namespace App\Models;

use App\Policies\ReminderPolicy;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
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
}
