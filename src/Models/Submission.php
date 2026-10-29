<?php

namespace DevFactory\AiSpam\Models;

use DevFactory\AiSpam\Database\Factories\SubmissionFactory;
use DevFactory\AiSpam\Enums\SubmissionStatus;
use DevFactory\AiSpam\Verdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * A form submission held back until the AI decides whether it is spam.
 *
 * @property int $id
 * @property string $form
 * @property SubmissionStatus $status
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $context
 * @property string|null $action Serialized job dispatched once the submission is approved.
 * @property array<string, mixed>|null $verdict
 * @property string|null $provider
 * @property string|null $model
 * @property string|null $error
 * @property Carbon|null $checked_at
 * @property Carbon|null $action_dispatched_at
 */
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory, MassPrunable;

    protected $table = 'ai_spam_submissions';

    protected $guarded = [];

    protected $hidden = ['action'];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'payload' => 'array',
            'context' => 'array',
            'action' => 'encrypted',
            'verdict' => 'array',
            'checked_at' => 'datetime',
            'action_dispatched_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('ai-spam.prune_after_days');

        return $days === null
            ? static::whereRaw('1 = 0')
            : static::where('created_at', '<', now()->subDays((int) $days));
    }

    public function isPending(): bool
    {
        return $this->status === SubmissionStatus::Pending;
    }

    public function verdict(): ?Verdict
    {
        return $this->verdict === null ? null : Verdict::fromArray($this->verdict);
    }

    /**
     * Move the submission to a new status, but only if it is currently in one of `$from`.
     *
     * The update is atomic so that concurrent workers can never both transition
     * (and dispatch the action of) the same submission.
     *
     * @param  list<SubmissionStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    public function transition(array $from, SubmissionStatus $to, array $attributes = []): bool
    {
        $this->fill(['status' => $to, ...$attributes]);

        $updated = static::query()
            ->whereKey($this->getKey())
            ->whereIn('status', array_map(fn (SubmissionStatus $status) => $status->value, $from))
            ->update($this->getDirty() + ['updated_at' => now()]);

        if ($updated === 0) {
            $this->refresh();

            return false;
        }

        $this->syncOriginal();

        return true;
    }

    /**
     * Dispatch the stored action, if any, exactly once.
     */
    public function dispatchAction(): void
    {
        if ($this->action === null || $this->action_dispatched_at !== null) {
            return;
        }

        Bus::dispatch(unserialize($this->action));

        $this->forceFill(['action_dispatched_at' => now()])->save();
    }

    protected static function newFactory(): SubmissionFactory
    {
        return SubmissionFactory::new();
    }
}
