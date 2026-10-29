<?php

namespace DevFactory\AiSpam;

use DevFactory\AiSpam\Enums\SpamReason;
use Illuminate\Support\Collection;

class AiSpam
{
    public function __construct(protected Honeypot $honeypot) {}

    /**
     * Hold a submission until the AI has checked it. Chain `thenNotify()` or
     * `thenDispatch()` to say what happens once it is approved, then `submit()`.
     *
     * @param  array<string, mixed>  $payload  The submitted fields, as sent to the AI.
     */
    public function hold(string $form, array $payload): PendingSubmission
    {
        return new PendingSubmission($form, $payload);
    }

    /**
     * Run the honeypot pre-filter on the given fields.
     *
     * @param  Collection<string, mixed>|array<string, mixed>  $fields
     */
    public function preFilter(Collection|array $fields): ?SpamReason
    {
        return $this->honeypot->check($fields);
    }
}
