<?php

namespace DevFactory\AiSpam\Events;

use DevFactory\AiSpam\Enums\SpamReason;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * The honeypot pre-filter rejected a submission.
 */
class SpamDetected
{
    use Dispatchable;

    public function __construct(public SpamReason $reason, public Request $request) {}
}
