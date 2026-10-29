<?php

namespace DevFactory\AiSpam;

final readonly class ClassifiedSubmission
{
    public function __construct(
        public Verdict $verdict,
        public ?string $provider,
        public ?string $model,
    ) {}
}
