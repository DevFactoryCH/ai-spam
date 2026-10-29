<?php

namespace DevFactory\AiSpam\Jobs;

use DevFactory\AiSpam\Classifier;
use DevFactory\AiSpam\Enums\SubmissionStatus;
use DevFactory\AiSpam\Events\SubmissionApproved;
use DevFactory\AiSpam\Events\SubmissionRejected;
use DevFactory\AiSpam\Events\SubmissionUnchecked;
use DevFactory\AiSpam\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Asks the AI whether a held submission is spam, then dispatches its action
 * (approved) or drops it (rejected). When every attempt fails, the action is
 * dispatched anyway so that genuine messages are never lost (fail open).
 */
class CheckSubmission implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Submission $submission)
    {
        $this->onConnection(config('ai-spam.queue.connection'));
        $this->onQueue(config('ai-spam.queue.name'));
    }

    public function tries(): int
    {
        return (int) config('ai-spam.queue.tries', 3);
    }

    /**
     * @return int|list<int>
     */
    public function backoff(): int|array
    {
        return config('ai-spam.queue.backoff', [30, 120, 600]);
    }

    public function uniqueId(): string
    {
        return (string) $this->submission->getKey();
    }

    public function handle(Classifier $classifier): void
    {
        if (! $this->submission->isPending()) {
            return;
        }

        $result = $classifier->classify($this->submission);

        $isSpam = $result->verdict->isSpamAt((float) config('ai-spam.threshold', 0.8));

        $transitioned = $this->submission->transition([SubmissionStatus::Pending], $isSpam ? SubmissionStatus::Rejected : SubmissionStatus::Approved, [
            'verdict' => $result->verdict->toArray(),
            'provider' => $result->provider,
            'model' => $result->model,
            'error' => null,
            'checked_at' => now(),
        ]);

        if (! $transitioned) {
            return;
        }

        if ($isSpam) {
            SubmissionRejected::dispatch($this->submission);

            return;
        }

        $this->submission->dispatchAction();

        SubmissionApproved::dispatch($this->submission);
    }

    /**
     * Every attempt failed: fail open.
     */
    public function failed(?Throwable $exception): void
    {
        $transitioned = $this->submission->transition([SubmissionStatus::Pending], SubmissionStatus::Unchecked, [
            'error' => $exception?->getMessage(),
            'checked_at' => now(),
        ]);

        if (! $transitioned) {
            return;
        }

        $this->submission->dispatchAction();

        SubmissionUnchecked::dispatch($this->submission, $exception);
    }
}
