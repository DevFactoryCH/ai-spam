<?php

namespace DevFactory\AiSpam;

use DevFactory\AiSpam\Enums\SpamReason;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Honeypot + encrypted timestamp pre-filter, rendered by the <x-ai-spam /> component.
 */
class Honeypot
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config) {}

    public function enabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    public function unrandomizedNameFieldName(): string
    {
        return $this->config['name_field_name'];
    }

    public function nameFieldName(): string
    {
        if (! $this->config['randomize_name_field_name']) {
            return $this->unrandomizedNameFieldName();
        }

        return $this->unrandomizedNameFieldName().'_'.Str::random();
    }

    public function validFromFieldName(): string
    {
        return $this->config['valid_from_field_name'];
    }

    public function encryptedValidFrom(): string
    {
        return encrypt(now()->addSeconds((int) $this->config['amount_of_seconds'])->getTimestamp());
    }

    public function withCsp(): bool
    {
        return (bool) $this->config['with_csp'];
    }

    /**
     * Check the submitted honeypot fields.
     *
     * Classic forms post a randomized honeypot name (`my_name_xxxx`), so the field is
     * looked up by prefix. When neither field is present the form is not protected
     * and the check passes, mirroring spatie/laravel-honeypot.
     *
     * @param  Collection<string, mixed>|array<string, mixed>  $fields
     */
    public function check(Collection|array $fields, bool $requireFields = false): ?SpamReason
    {
        if (! $this->enabled()) {
            return null;
        }

        $fields = Collection::wrap($fields);

        $nameFieldName = $fields->keys()
            ->first(fn ($key) => is_string($key) && Str::startsWith($key, $this->unrandomizedNameFieldName()));

        if (! $requireFields && $nameFieldName === null && ! $fields->has($this->validFromFieldName())) {
            return null;
        }

        if ($nameFieldName === null || ! in_array($fields->get($nameFieldName), [null, ''], true)) {
            return SpamReason::HoneypotFilled;
        }

        if (! $this->config['valid_from_timestamp']) {
            return null;
        }

        $validFrom = $this->decryptTimestamp($fields->get($this->validFromFieldName()));

        if ($validFrom === null) {
            return SpamReason::InvalidTimestamp;
        }

        return Date::createFromTimestamp($validFrom)->isFuture() ? SpamReason::TooFast : null;
    }

    private function decryptTimestamp(mixed $encrypted): ?int
    {
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $timestamp = decrypt($encrypted);
        } catch (DecryptException) {
            return null;
        }

        if (! is_int($timestamp) && ! (is_string($timestamp) && ctype_digit($timestamp))) {
            return null;
        }

        return (int) $timestamp > 0 ? (int) $timestamp : null;
    }
}
