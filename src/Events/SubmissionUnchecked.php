<?php

namespace DevFactory\AiSpam\Events;

use DevFactory\AiSpam\Models\Submission;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * The AI check kept failing, so the submission's action was dispatched unchecked.
 */
class SubmissionUnchecked
{
    use Dispatchable;

    public function __construct(public Submission $submission, public ?Throwable $exception = null) {}
}
