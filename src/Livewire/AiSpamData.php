<?php

namespace DevFactory\AiSpam\Livewire;

use DevFactory\AiSpam\Honeypot;
use Livewire\Wireable;

/**
 * Livewire-bound honeypot fields, keyed by their unrandomized names.
 */
class AiSpamData implements Wireable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public array $data = [])
    {
        if (filled($data)) {
            return;
        }

        $honeypot = app(Honeypot::class);

        $this->data = [
            $honeypot->unrandomizedNameFieldName() => '',
            $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toLivewire(): array
    {
        return $this->data;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromLivewire($value): static
    {
        return new static($value);
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }
}
