<?php

namespace DevFactory\AiSpam\View;

use DevFactory\AiSpam\Honeypot;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Renders the hidden honeypot + timestamp fields: `<x-ai-spam />` or
 * `<x-ai-spam livewire-model="aiSpam" />` in a Livewire component using WithAiSpam.
 */
class Fields extends Component
{
    public function __construct(public ?string $livewireModel = null) {}

    public function render(): View
    {
        $honeypot = app(Honeypot::class);

        return view('ai-spam::components.fields', [
            'enabled' => $honeypot->enabled(),
            'nameFieldName' => $honeypot->nameFieldName(),
            'unrandomizedNameFieldName' => $honeypot->unrandomizedNameFieldName(),
            'validFromFieldName' => $honeypot->validFromFieldName(),
            'encryptedValidFrom' => $honeypot->encryptedValidFrom(),
            'withCsp' => $honeypot->withCsp(),
        ]);
    }
}
