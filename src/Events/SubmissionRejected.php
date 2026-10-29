<?php

namespace DevFactory\AiSpam\Events;

use DevFactory\AiSpam\Models\Submission;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The AI judged the submission to be spam; its action was dropped.
 */
class SubmissionRejected
{
    use Dispatchable;

    public function __construct(public Submission $submission) {}
}
