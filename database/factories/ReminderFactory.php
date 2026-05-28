<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        $dueAt = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'remindable_type' => Document::class,
            'remindable_id' => Document::factory(),
            'title' => fake()->sentence(3),
            'notes' => fake()->optional()->sentence(),
            'due_at' => $dueAt,
            'remind_at' => fake()->optional()->dateTimeBetween('now', $dueAt),
            'status' => Reminder::STATUS_PENDING,
            'metadata' => [
                'source' => 'factory',
            ],
            'assigned_to_id' => User::factory(),
            'created_by_id' => User::factory(),
            'completed_at' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Reminder::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Reminder::STATUS_CANCELLED,
            'completed_at' => null,
        ]);
    }
}
