<?php

namespace App\Models;

use App\Policies\DocumentReminderPolicy;
use Database\Factories\DocumentReminderFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(DocumentReminderPolicy::class)]
class DocumentReminder extends Model
{
    /** @use HasFactory<DocumentReminderFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'document_id',
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
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
