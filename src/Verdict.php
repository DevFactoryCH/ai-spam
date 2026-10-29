<?php

namespace DevFactory\AiSpam;

use Illuminate\Contracts\Support\Arrayable;
use UnexpectedValueException;

/**
 * The classification result for one submission.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Verdict implements Arrayable
{
    /**
     * @param  float  $spamProbability  Probability (0 to 1) that the submission is spam.
     * @param  string|null  $category  The most likely category, see {@see Classifier::CATEGORIES}.
     * @param  array<string, float>  $categoryProbabilities
     */
    public function __construct(
        public float $spamProbability,
        public ?string $category = null,
        public array $categoryProbabilities = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws UnexpectedValueException when the stored verdict is incomplete.
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['spam_probability']) || ! is_numeric($data['spam_probability'])) {
            throw new UnexpectedValueException('Incomplete spam verdict: '.json_encode($data));
        }

        return new self(
            spamProbability: (float) $data['spam_probability'],
            category: $data['category'] ?? null,
            categoryProbabilities: $data['category_probabilities'] ?? [],
        );
    }

    /**
     * Whether the submission should be rejected at the given probability threshold.
     */
    public function isSpamAt(float $threshold): bool
    {
        return $this->spamProbability >= $threshold;
    }

    /**
     * @return array{spam_probability: float, category: string|null, category_probabilities: array<string, float>}
     */
    public function toArray(): array
    {
        return [
            'spam_probability' => $this->spamProbability,
            'category' => $this->category,
            'category_probabilities' => $this->categoryProbabilities,
        ];
    }
}
