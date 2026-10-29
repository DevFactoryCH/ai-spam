@if ($enabled)
    @if ($withCsp)
        <style @cspNonce>#{{ $nameFieldName }}_wrap { display: none; }</style>
    @endif
    <div id="{{ $nameFieldName }}_wrap" @unless ($withCsp) style="display: none" @endunless aria-hidden="true">
        <input id="{{ $nameFieldName }}"
               name="{{ $nameFieldName }}"
               type="text"
               value=""
               @if ($livewireModel) wire:model="{{ $livewireModel }}.{{ $unrandomizedNameFieldName }}" @endif
               autocomplete="off"
               tabindex="-1">
        <input name="{{ $validFromFieldName }}"
               type="text"
               value="{{ $encryptedValidFrom }}"
               @if ($livewireModel) wire:model="{{ $livewireModel }}.{{ $validFromFieldName }}" @endif
               autocomplete="off"
               tabindex="-1">
    </div>
@endif
