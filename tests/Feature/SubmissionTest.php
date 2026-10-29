<?php

use DevFactory\AiSpam\Enums\SubmissionStatus;
use DevFactory\AiSpam\Events\SubmissionApproved;
use DevFactory\AiSpam\Events\SubmissionRejected;
use DevFactory\AiSpam\Events\SubmissionReleased;
use DevFactory\AiSpam\Events\SubmissionUnchecked;
use DevFactory\AiSpam\Facades\AiSpam;
use DevFactory\AiSpam\Jobs\CheckSubmission;
use DevFactory\AiSpam\Jobs\SendHeldNotification;
use DevFactory\AiSpam\Models\Submission;
use DevFactory\AiSpam\Tests\ContactReceived;
use DevFactory\AiSpam\Tests\SubscribeToNewsletter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;

function holdContact(string $message = 'Bonjour, je souhaite des informations sur la formation.'): Submission
{
    return AiSpam::hold('contact', ['name' => 'Jeanne', 'email' => 'jeanne@example.ch', 'message' => $message, 'formation_id' => 12])
        ->context(['fill_duration_seconds' => 42.5])
        ->thenNotify(Notification::route('mail', 'info@example.ch'), new ContactReceived($message))
        ->submit();
}

function fakeSpamProbability(float $probability): void
{
    Classification::fake([['spam' => new BooleanAnswer($probability)]])->preventStrayClassifications();
}

it('stores the submission with an encrypted action and queues the check', function () {
    Queue::fake();

    $submission = holdContact();

    expect($submission->status)->toBe(SubmissionStatus::Pending)
        ->and($submission->payload['message'])->toContain('Bonjour')
        ->and($submission->context)->toBe(['fill_duration_seconds' => 42.5]);

    $raw = DB::table('ai_spam_submissions')->value('action');
    expect($raw)->not->toContain('ContactReceived')
        ->and(unserialize(decrypt($raw, false)))->toBeInstanceOf(SendHeldNotification::class);

    Queue::assertPushed(CheckSubmission::class, fn (CheckSubmission $job) => $job->submission->is($submission));
});

it('sends the notification when the AI approves the submission', function () {
    Notification::fake();
    Event::fake([SubmissionApproved::class]);
    fakeSpamProbability(0.05);

    $submission = holdContact()->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Approved)
        ->and($submission->verdict['spam_probability'])->toBe(0.05)
        ->and($submission->verdict['category'])->not->toBeNull()
        ->and($submission->checked_at)->not->toBeNull()
        ->and($submission->action_dispatched_at)->not->toBeNull();

    Notification::assertSentOnDemand(ContactReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'info@example.ch');
    Event::assertDispatched(SubmissionApproved::class);
});

it('drops the action when the AI rejects the submission', function () {
    Notification::fake();
    Event::fake([SubmissionRejected::class]);
    fakeSpamProbability(0.97);

    $submission = holdContact('Boost your SEO ranking today!')->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Rejected)
        ->and($submission->action_dispatched_at)->toBeNull();

    Notification::assertNothingSent();
    Event::assertDispatched(SubmissionRejected::class);
});

it('approves spam below the threshold', function () {
    Notification::fake();
    config(['ai-spam.threshold' => 0.8]);
    fakeSpamProbability(0.79);

    expect(holdContact()->refresh()->status)->toBe(SubmissionStatus::Approved);

    Notification::assertSentOnDemandTimes(ContactReceived::class, 1);
});

it('sends the payload and context but not ignored fields to the provider', function () {
    Notification::fake();
    fakeSpamProbability(0.1);

    holdContact();

    Classification::assertClassified(function (ClassificationPrompt $prompt) {
        return $prompt->state['form'] === 'contact'
            && $prompt->state['submission']['message'] === 'Bonjour, je souhaite des informations sur la formation.'
            && ! array_key_exists('formation_id', $prompt->state['submission'])
            && $prompt->state['context'] === ['fill_duration_seconds' => 42.5]
            && array_keys($prompt->questions) === ['spam', 'category'];
    });
});

it('uses the configured provider and model', function () {
    Notification::fake();
    config(['ai-spam.provider' => 'openai', 'ai-spam.model' => 'my-classifier']);
    fakeSpamProbability(0.1);

    $submission = holdContact()->refresh();

    expect($submission->provider)->toBe('openai')
        ->and($submission->model)->toBe('my-classifier');
});

it('fails open when every attempt failed', function () {
    Notification::fake();
    Event::fake([SubmissionUnchecked::class]);
    Queue::fake([CheckSubmission::class]);

    $submission = holdContact();

    (new CheckSubmission($submission))->failed(new RuntimeException('Provider down'));

    expect($submission->refresh()->status)->toBe(SubmissionStatus::Unchecked)
        ->and($submission->error)->toBe('Provider down');

    Notification::assertSentOnDemandTimes(ContactReceived::class, 1);
    Event::assertDispatched(SubmissionUnchecked::class);
});

it('rethrows provider errors so the job is retried', function () {
    Queue::fake();
    Classification::fake(fn () => throw new RuntimeException('Rate limited'));

    $submission = holdContact();

    expect(fn () => app()->call([new CheckSubmission($submission), 'handle']))->toThrow(RuntimeException::class);
    expect($submission->refresh()->status)->toBe(SubmissionStatus::Pending);
});

it('never dispatches the action twice when workers race', function () {
    Notification::fake();
    Queue::fake([CheckSubmission::class]);
    Classification::fake([['spam' => new BooleanAnswer(0.1)], ['spam' => new BooleanAnswer(0.1)]]);

    $id = holdContact()->id;
    [$first, $second] = [Submission::find($id), Submission::find($id)];

    app()->call([new CheckSubmission($first), 'handle']);
    app()->call([new CheckSubmission($second), 'handle']);
    (new CheckSubmission(Submission::find($id)))->failed(new RuntimeException('late failure'));

    expect(Submission::find($id)->status)->toBe(SubmissionStatus::Approved);
    Notification::assertSentOnDemandTimes(ContactReceived::class, 1);
});

it('dispatches any job as the action', function () {
    Bus::fake([SubscribeToNewsletter::class]);
    fakeSpamProbability(0.1);

    AiSpam::hold('newsletter', ['email' => 'jeanne@example.ch'])
        ->thenDispatch(new SubscribeToNewsletter('jeanne@example.ch'))
        ->submit();

    Bus::assertDispatched(SubscribeToNewsletter::class, fn ($job) => $job->email === 'jeanne@example.ch');
});

it('skips the AI and dispatches immediately when disabled', function () {
    Notification::fake();
    config(['ai-spam.enabled' => false]);
    Classification::fake()->preventStrayClassifications();

    expect(holdContact()->status)->toBe(SubmissionStatus::Skipped);

    Notification::assertSentOnDemandTimes(ContactReceived::class, 1);
    Classification::assertNothingClassified();
});

it('releases a rejected submission', function () {
    Notification::fake();
    Event::fake([SubmissionReleased::class]);
    fakeSpamProbability(0.99);

    $submission = holdContact();

    $this->artisan('ai-spam:release', ['id' => [$submission->id]])->assertSuccessful();

    expect($submission->refresh()->status)->toBe(SubmissionStatus::Released);
    Notification::assertSentOnDemandTimes(ContactReceived::class, 1);
    Event::assertDispatched(SubmissionReleased::class);

    $this->artisan('ai-spam:release', ['id' => [$submission->id]])->assertSuccessful();
    Notification::assertSentOnDemandTimes(ContactReceived::class, 1);
});

it('prunes old submissions', function () {
    Submission::factory()->create(['created_at' => now()->subDays(31)]);
    Submission::factory()->create();

    $this->artisan('model:prune', ['--model' => Submission::class])->assertSuccessful();

    expect(Submission::count())->toBe(1);
});
