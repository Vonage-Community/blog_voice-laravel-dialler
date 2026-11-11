<?php

use App\Models\VonageSetting;
use App\Services\ManagesVonageSettings;
use App\Services\VonageUserProvisioner;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings · Vonage Dialler')] class extends Component
{
    use ManagesVonageSettings;

    public function mount(): void
    {
        $this->fillFromSettings(VonageSetting::current());
    }

    public function save(VonageUserProvisioner $provisioner): void
    {
        $this->validate();

        $settings = VonageSetting::current();
        $settings->application_id = trim($this->applicationId);
        $settings->vonage_number = $this->normalisedVonageNumber();

        if (! $this->applyPrivateKey($settings)) {
            return;
        }

        $this->applyGreeting($settings);
        $this->applyAwsSettings($settings);

        $settings->save();

        $this->reset('privateKey', 'greeting', 'awsSecretAccessKey');

        $this->reportProvisioning($provisioner, $settings);
    }

    public function with(): array
    {
        $settings = VonageSetting::current();

        return [
            'hasPrivateKey' => filled($settings->private_key),
            'greetingFilename' => $settings->answerphone_filename,
            'hasAwsSecret' => filled($settings->aws_secret_access_key),
            'greetingUrl' => $settings->hasAnswerphoneGreeting() ? route('answerphone.audio', ['v' => $settings->greetingVersion()]) : null,
            'webhooksAreLocalOnly' => blank($settings->public_url) && Str::is(['localhost', '127.0.0.1', '*.localhost', '*.test', '*.local'], request()->getHost()),
            'answerUrl' => $settings->publicRoute('webhooks.answer'),
            'eventUrl' => $settings->publicRoute('webhooks.event'),
        ];
    }
};
?>

<div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
    <form wire:submit="save" class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-black/5">
        <h1 class="text-3xl font-semibold tracking-tight">Settings</h1>
        <p class="mt-2 text-sm text-black/60">Connect the dialler to your Vonage application.</p>

        @if ($statusMessage)
            <div @class([
                'mt-6 rounded-2xl px-4 py-3 text-sm font-medium',
                'bg-amber-50 text-amber-800 ring-1 ring-amber-200' => $statusIsWarning,
                'bg-vonage-purple/10 text-vonage-purple ring-1 ring-vonage-purple/20' => ! $statusIsWarning,
            ]) role="status">
                {{ $statusMessage }}
            </div>
        @endif

        <div class="mt-8 space-y-7">
            <div>
                <label for="applicationId" class="block text-sm font-semibold">Application ID</label>
                <input id="applicationId" type="text" wire:model="applicationId" placeholder="aaaaaaaa-bbbb-cccc-dddd-0123456789ab" autocomplete="off"
                    class="mt-2 w-full rounded-xl border border-black/15 px-4 py-3 font-mono text-sm outline-none transition focus:border-vonage-purple focus:ring-4 focus:ring-vonage-purple/15">
                @error('applicationId') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="vonageNumber" class="block text-sm font-semibold">Vonage number</label>
                <input id="vonageNumber" type="tel" wire:model="vonageNumber" placeholder="447700900000"
                    class="mt-2 w-full rounded-xl border border-black/15 px-4 py-3 font-mono text-sm outline-none transition focus:border-vonage-purple focus:ring-4 focus:ring-vonage-purple/15">
                <p class="mt-2 text-xs text-black/50">The number linked to your application, with its country code and no leading zero. Outbound calls use it as the caller ID.</p>
                @error('vonageNumber') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-setting-upload name="privateKey" label="Private key">
                @if ($privateKey)
                    <span class="font-medium">{{ $privateKey->getClientOriginalName() }}</span> <span class="text-black/50">ready to save</span>
                @elseif ($hasPrivateKey)
                    <span class="font-medium text-vonage-purple">private.key uploaded</span> <span class="text-black/50">· choose a file to replace it</span>
                @else
                    <span class="text-black/60">Upload <code class="font-mono">private.key</code></span>
                @endif
            </x-setting-upload>

            <div>
                <x-setting-upload name="greeting" label="Answerphone greeting" accept=".mp3,audio/mpeg">
                    @if ($greeting)
                        <span class="font-medium">{{ $greeting->getClientOriginalName() }}</span> <span class="text-black/50">ready to save</span>
                    @elseif ($greetingFilename)
                        <span class="font-medium text-vonage-purple">{{ $greetingFilename }}</span> <span class="text-black/50">· choose a file to replace it</span>
                    @else
                        <span class="text-black/60">Upload an <code class="font-mono">.mp3</code> to play when the answerphone is on</span>
                    @endif
                </x-setting-upload>

                @if ($greetingUrl)
                    <audio controls preload="none" src="{{ $greetingUrl }}" class="mt-3 w-full"></audio>
                @endif
            </div>

            <div class="border-t border-black/10 pt-7">
                <h2 class="text-lg font-semibold tracking-tight">Call recordings</h2>
                <p class="mt-1 text-sm text-black/60">The AWS keys and S3 bucket the dialler reads recordings from.</p>

                <x-aws-settings-fields :has-secret="$hasAwsSecret" class="mt-6" />
            </div>
        </div>

        <button type="submit" wire:loading.attr="disabled"
            class="vonage-gradient mt-10 inline-flex items-center rounded-full px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-vonage-purple/25 transition hover:opacity-90 disabled:opacity-50">
            <span wire:loading.remove wire:target="save">Save settings</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </form>

    <aside class="space-y-6">
        <div class="rounded-3xl bg-vonage-black p-6 text-white">
            <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-vonage-purple-light">Webhooks</h2>
            <p class="mt-2 text-sm text-white/70">Set these on the Voice capability of your Vonage application. They must be reachable from the internet.</p>

            @if ($webhooksAreLocalOnly)
                <p class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 ring-1 ring-amber-200" role="alert">
                    These addresses only work on this computer, so Vonage can't reach them and calls will fail. Run <code class="font-mono">php artisan vonage:tunnel</code> to make them public and update your Vonage application.
                </p>
            @endif

            <dl class="mt-5 space-y-4 text-sm">
                <div>
                    <dt class="text-white/50">Answer URL</dt>
                    <dd class="mt-1 break-all font-mono text-xs">{{ $answerUrl }}</dd>
                </div>
                <div>
                    <dt class="text-white/50">Event URL</dt>
                    <dd class="mt-1 break-all font-mono text-xs">{{ $eventUrl }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-3xl bg-white p-6 text-sm text-black/70 shadow-sm ring-1 ring-black/5">
            <h2 class="font-semibold text-vonage-ink">How calls are routed</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5">
                <li><strong>Answerphone on:</strong> callers hear your greeting and the console logs the call.</li>
                <li><strong>Answerphone off:</strong> the call rings the browser phone so you can answer it.</li>
            </ul>
        </div>
    </aside>
</div>
