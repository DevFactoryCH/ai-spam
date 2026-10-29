# AI Spam

AI-backed, queued spam protection for Laravel forms.

Form submissions go through a free honeypot pre-filter first. Those that pass are **held** in the database, and a queued job asks an AI provider, through the [Laravel AI SDK](https://laravel.com/docs/ai-sdk) classification API, how likely the submission is to be spam. Approved submissions then run the action you attached (send a notification, dispatch a job). Spam is dropped.

- Uses the SDK's **classification API**, which returns calibrated probabilities instead of free-form text, so there's no JSON to parse and nothing to go wrong.
- The visitor never waits for the AI. The check runs on your queue.
- **Fails open**: if the provider stays down after every retry, the action still runs and the submission is flagged `unchecked`. A real customer's message is never lost.
- Submitted data is untrusted: actions are stored encrypted, and the AI only sees the submitted fields.

## Installation

```bash
composer require devfactorych/ai-spam
php artisan vendor:publish --tag="ai-spam-migrations"
php artisan migrate
php artisan vendor:publish --tag="ai-spam-config" # optional
```

Configure a provider that supports classification in `config/ai.php` (`openai`, `openrouter`, `typesafe` or an `openai-compatible` provider), then point the package at it:

```dotenv
AI_SPAM_PROVIDER=openai            # null = ai.default_for_classification
AI_SPAM_MODEL=                     # null = the provider's default classification model
AI_SPAM_SITE_CONTEXT="A Swiss training organization offering adult education courses."
```

You need a running queue worker. On the `sync` queue the check runs during the request and provider errors reach the visitor.

## Usage

### 1. Add the fields to your form

```blade
{{-- Classic form --}}
<form method="POST" action="/contact">
    @csrf
    <x-ai-spam />
    ...
</form>

{{-- Livewire --}}
<form wire:submit="send">
    <x-ai-spam livewire-model="aiSpam" />
    ...
</form>
```

### 2. Run the pre-filter

Classic forms: add the `DevFactory\AiSpam\Http\Middleware\ProtectAgainstSpam` middleware to the route.

Livewire: use the `WithAiSpam` trait.

```php
use DevFactory\AiSpam\Livewire\Concerns\WithAiSpam;

class ContactForm extends Component
{
    use WithAiSpam;

    public function send(): void
    {
        if ($this->aiSpamPreFilter()) {
            abort(403);
        }

        // ...

        $this->resetAiSpam();
    }
}
```

### 3. Hold the submission instead of acting on it

```php
use DevFactory\AiSpam\Facades\AiSpam;
use Illuminate\Support\Facades\Notification;

AiSpam::hold(form: 'contact', payload: $validated)
    ->context(['fill_duration_seconds' => 12.4])    // optional extra signals for the AI
    ->thenNotify(
        Notification::route('mail', 'info@example.com'),
        new ContactNotification(...$validated),
    )
    ->submit();
```

`thenDispatch(new AnyJob(...))` works the same way for any other side effect, such as a newsletter subscription or a CRM sync. The action is serialized like a queued job, so models are stored by reference (`SerializesModels`).

## Lifecycle

| Status | Meaning |
| --- | --- |
| `pending` | Waiting for the AI check. |
| `approved` | Spam probability below `threshold`. The action was dispatched. |
| `rejected` | Spam probability at or above `threshold` (default `0.8`). The action was dropped. |
| `unchecked` | Every attempt failed. The action was dispatched anyway (fail open). |
| `skipped` | `AI_SPAM_ENABLED=false`. The action was dispatched immediately. |
| `released` | A rejected submission was approved manually. |

Each submission stores the verdict (`spam_probability`, `category` and the category probabilities), plus the provider and model that answered.

Events: `SpamDetected` (pre-filter), `SubmissionApproved`, `SubmissionRejected`, `SubmissionUnchecked`, `SubmissionReleased`.

Release a false positive:

```bash
php artisan ai-spam:release 42
```

Submissions hold personal data and are pruned after `prune_after_days` (default 30). Schedule `model:prune`:

```php
Schedule::command('model:prune', ['--model' => [\DevFactory\AiSpam\Models\Submission::class]])->daily();
```

## Testing your app

Use the SDK's classification fake:

```php
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\BooleanAnswer;

Classification::fake([['spam' => new BooleanAnswer(0.02)]]);   // approve
Classification::fake([['spam' => new BooleanAnswer(0.99)]]);   // reject
```

`Notification::fake()` and `Bus::fake()` work as usual on the dispatched actions.

## License

MIT
