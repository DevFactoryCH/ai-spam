<?php

namespace DevFactory\AiSpam\Database\Factories;

use DevFactory\AiSpam\Enums\SubmissionStatus;
use DevFactory\AiSpam\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'form' => 'contact',
            'status' => SubmissionStatus::Pending,
            'payload' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'message' => fake()->paragraph(),
            ],
        ];
    }

    public function status(SubmissionStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function withAction(object $job): static
    {
        return $this->state(['action' => serialize($job)]);
    }
}
