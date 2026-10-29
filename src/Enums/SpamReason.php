<?php

namespace DevFactory\AiSpam\Enums;

/**
 * Why the honeypot pre-filter flagged a submission as spam.
 */
enum SpamReason: string
{
    /** The hidden honeypot field was filled in, or is missing entirely. */
    case HoneypotFilled = 'honeypot_filled';

    /** The form was submitted faster than humanly plausible. */
    case TooFast = 'too_fast';

    /** The encrypted timestamp is missing or was tampered with. */
    case InvalidTimestamp = 'invalid_timestamp';
}
