@props(['name', 'label', 'accept' => null])

<div>
    <span class="block text-sm font-semibold">{{ $label }}</span>
    <label for="{{ $name }}" class="mt-2 flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-dashed border-black/20 px-4 py-4 transition hover:border-vonage-purple">
        <span class="text-sm">{{ $slot }}</span>
        <span class="rounded-full bg-vonage-black px-4 py-1.5 text-xs font-semibold text-white">Browse</span>
        <input id="{{ $name }}" type="file" @if ($accept) accept="{{ $accept }}" @endif wire:model="{{ $name }}" class="sr-only">
    </label>
    <div wire:loading wire:target="{{ $name }}" class="mt-2 text-xs text-black/50">Uploading…</div>
    @error($name) <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
