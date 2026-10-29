<?php

namespace DevFactory\AiSpam\Livewire\Concerns;

use DevFactory\AiSpam\Enums\SpamReason;
use DevFactory\AiSpam\Events\SpamDetected;
use DevFactory\AiSpam\Honeypot;
use DevFactory\AiSpam\Livewire\AiSpamData;
use Livewire\Component;

/**
 * Adds the honeypot pre-filter to a Livewire component. Render the fields with
 * `<x-ai-spam livewire-model="aiSpam" />` and call `aiSpamPreFilter()` on submit.
 *
 * @mixin Component
 */
trait WithAiSpam
{
    public AiSpamData $aiSpam;

    public function mountWithAiSpam(): void
    {
        $this->resetAiSpam();
    }

    /**
     * Check the honeypot fields, firing {@see SpamDetected} when the submission is spam.
     */
    protected function aiSpamPreFilter(): ?SpamReason
    {
        $reason = app(Honeypot::class)->check($this->aiSpam->data, requireFields: true);

        if ($reason !== null) {
            event(new SpamDetected($reason, request()));
        }

        return $reason;
    }

    /**
     * Restart the timestamp, e.g. after a successful submission.
     */
    protected function resetAiSpam(): void
    {
        $this->aiSpam = new AiSpamData;
    }
}
