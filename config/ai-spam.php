<?php

use DevFactory\AiSpam\Models\Submission;
use DevFactory\AiSpam\SpamResponders\BlankPageResponder;

return [

    /*
     * Master switch for the AI check. When disabled, held submissions are
     * approved straight away and their action is dispatched immediately.
     */
    'enabled' => env('AI_SPAM_ENABLED', true),

    /*
     * The Laravel AI SDK provider used to classify submissions. It must support
     * the classification API (openai, openrouter, typesafe or an
     * openai-compatible provider). Pass an array to fail over, e.g.
     * ['typesafe', 'openai']. Null uses the SDK's `ai.default_for_classification`.
     */
    'provider' => env('AI_SPAM_PROVIDER'),

    /*
     * The classification model to use. Null uses the provider's default
     * classification model.
     */
    'model' => env('AI_SPAM_MODEL'),

    /*
     * HTTP timeout (in seconds) for a single classification request.
     */
    'timeout' => (int) env('AI_SPAM_TIMEOUT', 30),

    /*
     * A submission is rejected when its spam probability (0 to 1) is at least
     * this value. Keep it high: rejecting a real customer costs more than
     * letting one spam message through.
     */
    'threshold' => (float) env('AI_SPAM_THRESHOLD', 0.8),

    /*
     * Describes the site so the AI can judge what a legitimate message looks
     * like, e.g. "A Swiss training organization offering adult education courses."
     */
    'site_context' => env('AI_SPAM_SITE_CONTEXT'),

    /*
     * Replace the instructions of the "is this spam?" question entirely.
     * Null uses the package's built-in instructions.
     */
    'instructions' => null,

    /*
     * Payload keys that are never sent to the AI provider (matched with
     * Str::is, so wildcards are allowed).
     */
    'ignore_fields' => ['_token', 'password*', '*_id'],

    /*
     * Queue settings for the CheckSubmission job.
     */
    'queue' => [
        'connection' => env('AI_SPAM_QUEUE_CONNECTION'),
        'name' => env('AI_SPAM_QUEUE'),
        'tries' => 3,
        'backoff' => [30, 120, 600],
    ],

    /*
     * The model used to store held submissions. It must extend
     * DevFactory\AiSpam\Models\Submission.
     */
    'model_class' => Submission::class,

    /*
     * Held submissions contain personal data. Rows older than this are
     * removed by `php artisan model:prune`. Null keeps them forever.
     */
    'prune_after_days' => 30,

    /*
     * Honeypot + timestamp pre-filter rendered by the <x-ai-spam /> component.
     * It rejects obvious bots for free, before any AI call is made.
     */
    'honeypot' => [
        'enabled' => env('AI_SPAM_HONEYPOT_ENABLED', true),

        /*
         * Any request that submits a non-empty value for this field is spam.
         */
        'name_field_name' => env('AI_SPAM_HONEYPOT_NAME', 'my_name'),

        /*
         * Appends a random string to the field name on classic forms.
         * Livewire forms always use the plain name.
         */
        'randomize_name_field_name' => env('AI_SPAM_HONEYPOT_RANDOMIZE', true),

        /*
         * Submissions sent faster than this after rendering the form are spam.
         */
        'valid_from_timestamp' => env('AI_SPAM_HONEYPOT_VALID_FROM_TIMESTAMP', true),

        'valid_from_field_name' => env('AI_SPAM_HONEYPOT_VALID_FROM', 'valid_from'),

        'amount_of_seconds' => (int) env('AI_SPAM_HONEYPOT_SECONDS', 3),

        /*
         * Hide the fields with a nonced <style> tag (requires a CSP nonce)
         * instead of an inline style attribute.
         */
        'with_csp' => env('AI_SPAM_HONEYPOT_WITH_CSP', false),

        /*
         * How the ProtectAgainstSpam middleware responds to spam.
         */
        'respond_to_spam_with' => BlankPageResponder::class,
    ],

];
