<?php

namespace DevFactory\AiSpam\Commands;

use DevFactory\AiSpam\Enums\SubmissionStatus;
use DevFactory\AiSpam\Events\SubmissionReleased;
use DevFactory\AiSpam\Models\Submission;
use Illuminate\Console\Command;

/**
 * Manually approve a submission the AI rejected (a false positive), or one stuck
 * pending, and dispatch its action.
 */
class ReleaseCommand extends Command
{
    protected $signature = 'ai-spam:release {id* : The ID(s) of the submission(s) to release}';

    protected $description = 'Approve rejected AI spam submissions and dispatch their action';

    public function handle(): int
    {
        /** @var class-string<Submission> $modelClass */
        $modelClass = config('ai-spam.model_class', Submission::class);

        $status = self::SUCCESS;

        foreach ((array) $this->argument('id') as $id) {
            $submission = $modelClass::find($id);

            if ($submission === null) {
                $this->components->error("Submission [{$id}] not found.");
                $status = self::FAILURE;

                continue;
            }

            if (! $submission->transition([SubmissionStatus::Rejected, SubmissionStatus::Pending], SubmissionStatus::Released)) {
                $this->components->warn("Submission [{$id}] is {$submission->status->value}, nothing to release.");

                continue;
            }

            $submission->dispatchAction();

            SubmissionReleased::dispatch($submission);

            $this->components->info("Submission [{$id}] released.");
        }

        return $status;
    }
}
