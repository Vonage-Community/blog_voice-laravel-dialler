<?php

use App\Models\VonageSetting;
use App\Services\VonageTokenGenerator;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Phone · Vonage Dialler')] class extends Component
{
    public bool $answerphoneEnabled = false;

    public function mount(): void
    {
        $this->answerphoneEnabled = VonageSetting::current()->answerphone_enabled;
    }

    public function updatedAnswerphoneEnabled(bool $enabled): void
    {
        VonageSetting::current()->update(['answerphone_enabled' => $enabled]);
    }

    /**
     * Issue a Client SDK token so the browser can sign in to Vonage.
     */
    #[Renderless]
    public function issueToken(VonageTokenGenerator $tokens): ?string
    {
        $settings = VonageSetting::current();

        return $settings->isReadyToDial() ? $tokens->clientToken($settings) : null;
    }

    public function with(): array
    {
        $settings = VonageSetting::current();

        return [
            'isReadyToDial' => $settings->isReadyToDial(),
            'vonageNumber' => $settings->vonage_number,
            'hasGreeting' => $settings->hasAnswerphoneGreeting(),
        ];
    }
};
?>

<div>
    @unless ($isReadyToDial)
        <livewire:setup-wizard />
    @endunless

    <div @unless ($isReadyToDial) inert @endunless class="grid items-start gap-8 lg:grid-cols-[24rem_1fr]">
        <div x-data="phone({ configured: @js($isReadyToDial) })" @keydown.window="handleKey($event)"
            class="mx-auto w-full max-w-sm rounded-[2.75rem] bg-vonage-black p-3 shadow-2xl shadow-black/30">
            <div class="rounded-[2.25rem] bg-vonage-ink px-6 pb-8 pt-6 text-white ring-1 ring-white/5">
                {{-- Status bar --}}
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <x-vonage-logo class="h-3.5 w-auto" />
                        <span class="font-mono text-white/60">{{ $vonageNumber ? '+'.$vonageNumber : 'No number set' }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full" :class="statusDotClass"></span>
                        <span class="text-white/70" x-text="statusLabel"></span>
                    </div>
                </div>

                {{-- Display --}}
                <div class="mt-8 flex h-28 flex-col items-center justify-center text-center">
                    <template x-if="state === 'incoming'">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-vonage-purple-light">Incoming call</p>
                            <p class="mt-2 font-mono text-3xl" x-text="formatNumber(incomingFrom)"></p>
                        </div>
                    </template>
                    <template x-if="state !== 'incoming'">
                        <div class="w-full">
                            <p class="min-h-10 truncate font-mono text-3xl tracking-wider" x-text="number || placeholder"
                                :class="number ? 'text-white' : 'text-white/25'"></p>
                            <p class="mt-2 font-mono text-sm text-vonage-purple-light" x-show="state === 'in-call'" x-text="elapsed"></p>
                            <p class="mt-2 text-sm text-white/50" x-show="state === 'dialling'">Calling…</p>
                            <p class="mt-2 text-sm text-red-300" x-show="errorMessage" x-text="errorMessage"></p>
                        </div>
                    </template>
                </div>

                {{-- Keypad --}}
                <div class="mt-6 grid grid-cols-3 gap-4" x-show="state !== 'incoming'">
                    @foreach ([['1', ''], ['2', 'ABC'], ['3', 'DEF'], ['4', 'GHI'], ['5', 'JKL'], ['6', 'MNO'], ['7', 'PQRS'], ['8', 'TUV'], ['9', 'WXYZ'], ['*', ''], ['0', '+'], ['#', '']] as [$digit, $letters])
                        <button type="button" wire:key="key-{{ $digit }}" @click="press('{{ $digit }}')"
                            class="mx-auto flex size-18 flex-col items-center justify-center rounded-full bg-white/[0.07] transition hover:bg-white/15 active:scale-95 active:bg-vonage-purple">
                            <span class="text-2xl font-medium leading-none">{{ $digit }}</span>
                            <span class="mt-1 h-3 text-[0.6rem] font-semibold tracking-[0.2em] text-white/40">{{ $letters }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Incoming call controls --}}
                <div class="mt-10 flex justify-around" x-show="state === 'incoming'" x-cloak>
                    <button type="button" @click="reject()" class="flex flex-col items-center gap-2 text-xs text-white/70">
                        <span class="flex size-18 items-center justify-center rounded-full bg-red-500 transition hover:bg-red-400 active:scale-95">
                            <svg class="size-8 rotate-[135deg]" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1L6.6 10.8Z"/></svg>
                        </span>
                        Decline
                    </button>
                    <button type="button" @click="answer()" class="flex flex-col items-center gap-2 text-xs text-white/70">
                        <span class="flex size-18 animate-pulse items-center justify-center rounded-full bg-emerald-500 transition hover:bg-emerald-400 active:scale-95">
                            <svg class="size-8" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1L6.6 10.8Z"/></svg>
                        </span>
                        Answer
                    </button>
                </div>

                {{-- Call controls --}}
                <div class="mt-6 grid grid-cols-3 items-center gap-4" x-show="state !== 'incoming'">
                    <button type="button" @click="toggleMute()" :disabled="state !== 'in-call'"
                        class="mx-auto flex size-12 items-center justify-center rounded-full text-white/60 transition hover:bg-white/10 disabled:opacity-20"
                        :class="muted && 'bg-white text-vonage-black hover:bg-white'" :aria-pressed="muted" title="Mute">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/><path x-show="muted" d="M3 3l18 18"/></svg>
                    </button>

                    <template x-if="state === 'in-call' || state === 'dialling'">
                        <button type="button" @click="hangup()" title="Hang up"
                            class="mx-auto flex size-18 items-center justify-center rounded-full bg-red-500 text-white shadow-lg shadow-red-500/30 transition hover:bg-red-400 active:scale-95">
                            <svg class="size-8 rotate-[135deg]" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1L6.6 10.8Z"/></svg>
                        </button>
                    </template>
                    <template x-if="state !== 'in-call' && state !== 'dialling'">
                        <button type="button" @click="call()" :disabled="state !== 'ready' || ! number" title="Call"
                            class="vonage-gradient mx-auto flex size-18 items-center justify-center rounded-full text-white shadow-lg shadow-vonage-purple/30 transition hover:opacity-90 active:scale-95 disabled:opacity-30">
                            <svg class="size-8" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1L6.6 10.8Z"/></svg>
                        </button>
                    </template>

                    <button type="button" @click="backspace()" :disabled="! number || state === 'in-call'" title="Delete"
                        class="mx-auto flex size-12 items-center justify-center rounded-full text-white/60 transition hover:bg-white/10 disabled:opacity-20">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 5H9l-6 7 6 7h12a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1ZM17 9l-6 6M11 9l6 6"/></svg>
                    </button>
                </div>

                {{-- Answerphone switch --}}
                <label class="mt-8 flex cursor-pointer items-center justify-between rounded-2xl bg-white/[0.05] px-4 py-3 ring-1 ring-white/10">
                    <span>
                        <span class="block text-sm font-semibold">Answerphone</span>
                        <span class="block text-xs text-white/50">
                            @if ($answerphoneEnabled)
                                {{ $hasGreeting ? 'Callers hear your greeting' : 'Callers hear a spoken message' }}
                            @else
                                Incoming calls ring this phone
                            @endif
                        </span>
                    </span>
                    <input type="checkbox" wire:model.live="answerphoneEnabled" class="peer sr-only" role="switch">
                    <span class="relative h-7 w-12 rounded-full bg-white/20 transition peer-checked:bg-vonage-purple peer-focus-visible:ring-4 peer-focus-visible:ring-vonage-purple/40 after:absolute after:left-1 after:top-1 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                </label>
            </div>
        </div>

        <livewire:call-console />
    </div>
</div>
