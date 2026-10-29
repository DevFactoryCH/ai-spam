<?php

namespace DevFactory\AiSpam\Events;

use DevFactory\AiSpam\Models\Submission;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The AI judged the submission legitimate and its action was dispatched.
 */
class SubmissionApproved
{
    use Dispatchable;

    public function __construct(public Submission $submission) {}
}
