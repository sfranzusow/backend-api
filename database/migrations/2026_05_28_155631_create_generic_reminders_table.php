<?php

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('remind_at')->nullable();
            $table->enum('status', ['pending', 'done', 'cancelled'])->default('pending');
            $table->json('metadata')->nullable();
            $table->foreignId('assigned_to_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to_id', 'status', 'due_at']);
            $table->index(['remindable_type', 'remindable_id', 'status', 'due_at'], 'reminders_remindable_status_due_at_index');
        });

        DB::table('document_reminders')
            ->orderBy('id')
            ->cursor()
            ->each(function (object $documentReminder): void {
                DB::table('reminders')->insert([
                    'id' => $documentReminder->id,
                    'remindable_type' => Document::class,
                    'remindable_id' => $documentReminder->document_id,
                    'title' => $documentReminder->title,
                    'notes' => $documentReminder->notes,
                    'due_at' => $documentReminder->due_at,
                    'remind_at' => $documentReminder->remind_at,
                    'status' => $documentReminder->status,
                    'metadata' => $documentReminder->metadata,
                    'assigned_to_id' => $documentReminder->assigned_to_id,
                    'created_by_id' => $documentReminder->created_by_id,
                    'completed_at' => $documentReminder->completed_at,
                    'created_at' => $documentReminder->created_at,
                    'updated_at' => $documentReminder->updated_at,
                ]);
            });

        Schema::dropIfExists('document_reminders');
    }

    public function down(): void
    {
        Schema::create('document_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('remind_at')->nullable();
            $table->enum('status', ['pending', 'done', 'cancelled'])->default('pending');
            $table->json('metadata')->nullable();
            $table->foreignId('assigned_to_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'status', 'due_at']);
            $table->index(['assigned_to_id', 'status', 'due_at']);
        });

        DB::table('reminders')
            ->where('remindable_type', Document::class)
            ->orderBy('id')
            ->cursor()
            ->each(function (object $reminder): void {
                DB::table('document_reminders')->insert([
                    'id' => $reminder->id,
                    'document_id' => $reminder->remindable_id,
                    'title' => $reminder->title,
                    'notes' => $reminder->notes,
                    'due_at' => $reminder->due_at,
                    'remind_at' => $reminder->remind_at,
                    'status' => $reminder->status,
                    'metadata' => $reminder->metadata,
                    'assigned_to_id' => $reminder->assigned_to_id,
                    'created_by_id' => $reminder->created_by_id,
                    'completed_at' => $reminder->completed_at,
                    'created_at' => $reminder->created_at,
                    'updated_at' => $reminder->updated_at,
                ]);
            });

        Schema::dropIfExists('reminders');
    }
};
