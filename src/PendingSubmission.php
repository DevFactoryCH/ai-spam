<?php

namespace DevFactory\AiSpam;

use DevFactory\AiSpam\Enums\SubmissionStatus;
use DevFactory\AiSpam\Jobs\CheckSubmission;
use DevFactory\AiSpam\Jobs\SendHeldNotification;
use DevFactory\AiSpam\Models\Submission;
use Illuminate\Notifications\Notification;

/**
 * Fluent builder returned by `AiSpam::hold()`.
 */
class PendingSubmission
{
    /** @var array<string, mixed> */
    protected array $context = [];

    protected ?object $action = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        protected string $form,
        protected array $payload,
    ) {}

    /**
     * Extra information about the submission (fill time, user agent, …) given to the AI.
     *
     * @param  array<string, mixed>  $context
     */
    public function context(array $context): static
    {
        $this->context = [...$this->context, ...$context];

        return $this;
    }

    /**
     * Dispatch the given job once the submission is approved.
     */
    public function thenDispatch(object $job): static
    {
        $this->action = $job;

        return $this;
    }

    /**
     * Send the given notification once the submission is approved, e.g.
     * `->thenNotify(Notification::route('mail', $to), new ContactNotification(...))`.
     *
     * @param  mixed  $notifiables  A notifiable, an array or collection of them, or an AnonymousNotifiable.
     * @param  list<string>|null  $channels
     */
    public function thenNotify(mixed $notifiables, Notification $notification, ?array $channels = null): static
    {
        return $this->thenDispatch(new SendHeldNotification($notifiables, $notification, $channels));
    }

    /**
     * Store the submission and queue the AI check, or dispatch the action
     * straight away when the check is disabled.
     */
    public function submit(): Submission
    {
        /** @var class-string<Submission> $modelClass */
        $modelClass = config('ai-spam.model_class', Submission::class);

        $enabled = (bool) config('ai-spam.enabled');

        $submission = $modelClass::create([
            'form' => $this->form,
            'status' => $enabled ? SubmissionStatus::Pending : SubmissionStatus::Skipped,
            'payload' => $this->payload,
            'context' => $this->context ?: null,
            'action' => $this->action ? serialize($this->action) : null,
        ]);

        if ($enabled) {
            CheckSubmission::dispatch($submission);
        } else {
            $submission->dispatchAction();
        }

        return $submission;
    }
}
