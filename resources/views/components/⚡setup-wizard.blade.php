<?php

use App\Models\VonageSetting;
use App\Services\ManagesVonageSettings;
use App\Services\VonageUserProvisioner;
use Livewire\Component;

new class extends Component
{
    use ManagesVonageSettings;

    /**
     * The setup modals in the order they are shown.
     *
     * @var array<int, string>
     */
    private const array STEPS = ['applicationId', 'privateKey', 'vonageNumber', 'greeting', 'aws'];

    /**
     * The form properties the AWS modal collects together.
     *
     * @var array<int, string>
     */
    private const array AWS_FIELDS = ['awsAccessKeyId', 'awsSecretAccessKey', 'awsRegion', 'awsBucket'];

    public string $step = 'applicationId';

    public function mount(): void
    {
        $settings = VonageSetting::current();

        $this->fillFromSettings($settings);
        $this->step = $this->firstMissingStep($settings);
    }

    /**
     * Save the current step, then move on or open the dialler once nothing is left to set up.
     */
    public function save(VonageUserProvisioner $provisioner): void
    {
        $this->validateCurrentStep();

        $this->reset('statusMessage', 'statusIsWarning');

        $settings = VonageSetting::current();

        match ($this->step) {
            'applicationId' => $settings->application_id = trim($this->applicationId),
            'vonageNumber' => $settings->vonage_number = $this->normalisedVonageNumber(),
            'greeting' => $this->applyGreeting($settings),
            'aws' => $this->applyAwsSettings($settings),
            default => null,
        };

        if (! $this->applyPrivateKey($settings)) {
            return;
        }

        $settings->save();

        $this->reset('privateKey', 'greeting', 'awsSecretAccessKey');

        if (in_array($this->step, ['applicationId', 'privateKey'], true) && $settings->isConfigured()) {
            $this->reportProvisioning($provisioner, $settings);
        }

        if ($settings->isReadyToDial()) {
            $this->redirectRoute('phone', navigate: true);

            return;
        }

        $this->step = self::STEPS[$this->stepIndex() + 1] ?? $this->firstMissingStep($settings);
    }

    public function back(): void
    {
        $this->resetValidation();
        $this->reset('privateKey', 'greeting', 'awsSecretAccessKey');

        $this->step = self::STEPS[max($this->stepIndex() - 1, 0)];
    }

    /**
     * Require a value for each field on this step, unless it is already saved and write-only.
     */
    private function validateCurrentStep(): void
    {
        $settings = VonageSetting::current();

        $savedFields = array_keys(array_filter([
            'privateKey' => filled($settings->private_key),
            'greeting' => $settings->hasAnswerphoneGreeting(),
            'awsSecretAccessKey' => filled($settings->aws_secret_access_key),
        ]));

        $fields = $this->step === 'aws' ? self::AWS_FIELDS : [$this->step];

        $rules = [];

        foreach ($fields as $field) {
            $rules[$field] = in_array($field, $savedFields, true) ? $this->rules()[$field] : ['required', ...$this->rules()[$field]];
        }

        $this->validate($rules);
    }

    private function firstMissingStep(VonageSetting $settings): string
    {
        return match (true) {
            blank($settings->application_id) => 'applicationId',
            blank($settings->private_key) => 'privateKey',
            blank($settings->vonage_number) => 'vonageNumber',
            ! $settings->hasAnswerphoneGreeting() => 'greeting',
            default => 'aws',
        };
    }

    private function stepIndex(): int
    {
        return (int) array_search($this->step, self::STEPS, true);
    }

    public function with(): array
    {
        $settings = VonageSetting::current();

        return [
            'stepNumber' => $this->stepIndex() + 1,
            'stepCount' => count(self::STEPS),
            'hasPrivateKey' => filled($settings->private_key),
            'greetingFilename' => $settings->answerphone_filename,
            'hasAwsSecret' => filled($settings->aws_secret_access_key),
        ];
    }
};
?>

<div class="fixed inset-0 z-50 overflow-y-auto bg-vonage-black/70 backdrop-blur-sm">
    <div class="flex min-h-full items-center justify-center p-6">
        <form wire:submit="save" wire:key="setup-step-{{ $step }}" role="dialog" aria-modal="true" aria-labelledby="setup-title"
            class="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl">
            <div class="vonage-gradient h-1"></div>

            <div class="p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-vonage-purple">Set up your dialler · Step {{ $stepNumber }} of {{ $stepCount }}</p>

                <h2 id="setup-title" class="mt-3 text-2xl font-semibold tracking-tight">
                    @switch($step)
                        @case('applicationId') Add your application ID @break
                        @case('privateKey') Upload your private key @break
                        @case('vonageNumber') Add your Vonage number @break
                        @case('greeting') Upload your answerphone greeting @break
                        @default Connect your recordings bucket
                    @endswitch
                </h2>

                @if ($statusMessage && $statusIsWarning)
                    <div class="mt-5 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 ring-1 ring-amber-200" role="status">
                        {{ $statusMessage }}
                    </div>
                @endif

                <div class="mt-6">
                    @switch($step)
                        @case('applicationId')
                            <label for="applicationId" class="block text-sm font-semibold">Application ID</label>
                            <input id="applicationId" type="text" wire:model="applicationId" x-init="$el.focus()" placeholder="aaaaaaaa-bbbb-cccc-dddd-0123456789ab" autocomplete="off"
                                class="mt-2 w-full rounded-xl border border-black/15 px-4 py-3 font-mono text-sm outline-none transition focus:border-vonage-purple focus:ring-4 focus:ring-vonage-purple/15">
                            <p class="mt-2 text-xs text-black/50">Find it on your application in the Vonage dashboard.</p>
                            @error('applicationId') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            @break

                        @case('privateKey')
                            <x-setting-upload name="privateKey" label="Private key">
                                @if ($privateKey)
                                    <span class="font-medium">{{ $privateKey->getClientOriginalName() }}</span> <span class="text-black/50">ready to save</span>
                                @elseif ($hasPrivateKey)
                                    <span class="font-medium text-vonage-purple">private.key uploaded</span> <span class="text-black/50">· choose a file to replace it</span>
                                @else
                                    <span class="text-black/60">Upload <code class="font-mono">private.key</code></span>
                                @endif
                            </x-setting-upload>
                            <p class="mt-2 text-xs text-black/50">The file downloaded when you generated the application's keys.</p>
                            @break

                        @case('vonageNumber')
                            <label for="vonageNumber" class="block text-sm font-semibold">Vonage number</label>
                            <input id="vonageNumber" type="tel" wire:model="vonageNumber" x-init="$el.focus()" placeholder="447700900000"
                                class="mt-2 w-full rounded-xl border border-black/15 px-4 py-3 font-mono text-sm outline-none transition focus:border-vonage-purple focus:ring-4 focus:ring-vonage-purple/15">
                            <p class="mt-2 text-xs text-black/50">The number linked to your application, with its country code and no leading zero. Outbound calls use it as the caller ID.</p>
                            @error('vonageNumber') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            @break

                        @case('greeting')
                            <x-setting-upload name="greeting" label="Answerphone greeting" accept=".mp3,audio/mpeg">
                                @if ($greeting)
                                    <span class="font-medium">{{ $greeting->getClientOriginalName() }}</span> <span class="text-black/50">ready to save</span>
                                @elseif ($greetingFilename)
                                    <span class="font-medium text-vonage-purple">{{ $greetingFilename }}</span> <span class="text-black/50">· choose a file to replace it</span>
                                @else
                                    <span class="text-black/60">Upload an <code class="font-mono">.mp3</code> to play when the answerphone is on</span>
                                @endif
                            </x-setting-upload>
                            @break

                        @default
                            <x-aws-settings-fields :has-secret="$hasAwsSecret" autofocus />
                    @endswitch
                </div>

                <div class="mt-8 flex items-center justify-between">
                    @if ($stepNumber > 1)
                        <button type="button" wire:click="back" class="rounded-full px-4 py-2 text-sm font-medium text-black/60 transition hover:bg-black/5 hover:text-black">Back</button>
                    @else
                        <span></span>
                    @endif

                    <button type="submit" wire:loading.attr="disabled"
                        class="vonage-gradient inline-flex items-center rounded-full px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-vonage-purple/25 transition hover:opacity-90 disabled:opacity-50">
                        <span wire:loading.remove wire:target="save">{{ $stepNumber === $stepCount ? 'Finish' : 'Save and continue' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
