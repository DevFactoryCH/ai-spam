<?php

use DevFactory\AiSpam\Enums\SpamReason;
use DevFactory\AiSpam\Honeypot;
use DevFactory\AiSpam\Http\Middleware\ProtectAgainstSpam;
use Illuminate\Support\Facades\Route;

function honeypotFields(array $overrides = []): array
{
    return [
        'my_name_abc123' => '',
        'valid_from' => encrypt(now()->subSecond()->getTimestamp()),
        ...$overrides,
    ];
}

it('passes a valid submission', function () {
    expect(app(Honeypot::class)->check(honeypotFields()))->toBeNull();
});

it('detects a filled honeypot', function () {
    expect(app(Honeypot::class)->check(honeypotFields(['my_name_abc123' => 'bot'])))
        ->toBe(SpamReason::HoneypotFilled);
});

it('detects a submission sent too fast', function () {
    expect(app(Honeypot::class)->check(honeypotFields(['valid_from' => encrypt(now()->addMinute()->getTimestamp())])))
        ->toBe(SpamReason::TooFast);
});

it('accepts a timestamp encrypted as a string', function () {
    expect(app(Honeypot::class)->check(honeypotFields(['valid_from' => encrypt((string) now()->addMinute()->getTimestamp())])))
        ->toBe(SpamReason::TooFast);
});

it('detects a tampered timestamp', function () {
    expect(app(Honeypot::class)->check(honeypotFields(['valid_from' => 'not-encrypted'])))
        ->toBe(SpamReason::InvalidTimestamp);
});

it('ignores forms without honeypot fields unless they are required', function () {
    $honeypot = app(Honeypot::class);

    expect($honeypot->check(['email' => 'a@b.ch']))->toBeNull()
        ->and($honeypot->check(['email' => 'a@b.ch'], requireFields: true))->toBe(SpamReason::HoneypotFilled);
});

it('passes everything when disabled', function () {
    config(['ai-spam.honeypot.enabled' => false]);

    expect(app(Honeypot::class)->check(honeypotFields(['my_name_abc123' => 'bot'])))->toBeNull();
});

it('renders the blade component', function () {
    $html = (string) $this->blade('<form><x-ai-spam /></form>');

    expect($html)->toContain('name="my_name_')
        ->toContain('name="valid_from"')
        ->toContain('style="display: none"');
});

it('binds the fields to livewire', function () {
    $html = (string) $this->blade('<x-ai-spam livewire-model="aiSpam" />');

    expect($html)->toContain('wire:model="aiSpam.my_name"')
        ->toContain('wire:model="aiSpam.valid_from"');
});

it('blocks spam with the middleware', function () {
    Route::post('/contact', fn () => 'sent')->middleware(ProtectAgainstSpam::class);

    $this->post('/contact', honeypotFields(['my_name_abc123' => 'bot']))->assertOk()->assertContent('');
    $this->post('/contact', honeypotFields())->assertOk()->assertContent('sent');
});
