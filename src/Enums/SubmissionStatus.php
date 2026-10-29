<?php

namespace DevFactory\AiSpam\Enums;

enum SubmissionStatus: string
{
    /** Waiting for the AI check. */
    case Pending = 'pending';

    /** The AI judged the submission legitimate; its action was dispatched. */
    case Approved = 'approved';

    /** The AI judged the submission to be spam; its action was dropped. */
    case Rejected = 'rejected';

    /** The AI check kept failing, so the action was dispatched unchecked (fail open). */
    case Unchecked = 'unchecked';

    /** The AI check is disabled; the action was dispatched immediately. */
    case Skipped = 'skipped';

    /** A rejected submission was manually approved and its action dispatched. */
    case Released = 'released';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
