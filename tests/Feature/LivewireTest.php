<?php

use DevFactory\AiSpam\Enums\SpamReason;
use DevFactory\AiSpam\Events\SpamDetected;
use DevFactory\AiSpam\Livewire\Concerns\WithAiSpam;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Livewire;

class AiSpamContactForm extends Component
{
    use WithAiSpam;

    public ?string $result = null;

    public function send(): void
    {
        $this->result = $this->aiSpamPreFilter()?->value ?? 'sent';
    }

    public function render(): string
    {
        return '<form wire:submit="send"><x-ai-spam livewire-model="aiSpam" /></form>';
    }
}

it('round-trips the honeypot data and passes a human submission', function () {
    $this->travel(5)->seconds();

    Livewire::test(AiSpamContactForm::class)
        ->tap(fn () => $this->travel(10)->seconds())
        ->call('send')
        ->assertSet('result', 'sent');
});

it('flags a filled honeypot and fires an event', function () {
    Event::fake([SpamDetected::class]);

    Livewire::test(AiSpamContactForm::class)
        ->set('aiSpam.my_name', 'bot')
        ->tap(fn () => $this->travel(10)->seconds())
        ->call('send')
        ->assertSet('result', SpamReason::HoneypotFilled->value);

    Event::assertDispatched(SpamDetected::class, fn (SpamDetected $event) => $event->reason === SpamReason::HoneypotFilled);
});

it('flags a submission sent too fast', function () {
    Livewire::test(AiSpamContactForm::class)
        ->call('send')
        ->assertSet('result', SpamReason::TooFast->value);
});
