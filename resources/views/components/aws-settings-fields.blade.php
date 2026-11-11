@props(['hasSecret' => false, 'autofocus' => false])

@php
    $fields = [
        ['name' => 'awsAccessKeyId', 'label' => 'AWS access key ID', 'type' => 'text', 'placeholder' => 'AKIAIOSFODNN7EXAMPLE', 'hint' => null],
        ['name' => 'awsSecretAccessKey', 'label' => 'AWS secret access key', 'type' => 'password', 'placeholder' => $hasSecret ? '••••••••••••••••' : '', 'hint' => $hasSecret ? 'Saved. Leave blank to keep it.' : 'Stored encrypted and never shown again.'],
        ['name' => 'awsRegion', 'label' => 'AWS region', 'type' => 'text', 'placeholder' => 'eu-west-2', 'hint' => null],
        ['name' => 'awsBucket', 'label' => 'S3 bucket', 'type' => 'text', 'placeholder' => 'dialler-recordings', 'hint' => 'The keys need permission to list this bucket.'],
    ];
@endphp

<div {{ $attributes->class('space-y-5') }}>
    @foreach ($fields as $field)
        <div wire:key="aws-field-{{ $field['name'] }}">
            <label for="{{ $field['name'] }}" class="block text-sm font-semibold">{{ $field['label'] }}</label>
            <input id="{{ $field['name'] }}" type="{{ $field['type'] }}" wire:model="{{ $field['name'] }}" placeholder="{{ $field['placeholder'] }}" autocomplete="off"
                @if ($autofocus && $loop->first) x-init="$el.focus()" @endif
                class="mt-2 w-full rounded-xl border border-black/15 px-4 py-3 font-mono text-sm outline-none transition focus:border-vonage-purple focus:ring-4 focus:ring-vonage-purple/15">
            @if ($field['hint'])
                <p class="mt-2 text-xs text-black/50">{{ $field['hint'] }}</p>
            @endif
            @error($field['name']) <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    @endforeach
</div>
