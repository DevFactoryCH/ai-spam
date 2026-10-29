<?php

namespace DevFactory\AiSpam\Events;

use DevFactory\AiSpam\Models\Submission;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A rejected submission was manually approved and its action dispatched.
 */
class SubmissionReleased
{
    use Dispatchable;

    public function __construct(public Submission $submission) {}
}
