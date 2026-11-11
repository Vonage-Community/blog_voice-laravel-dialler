<?php

use App\Enums\CallEventType;
use App\Models\CallEvent;
use App\Models\VonageSetting;
use App\Services\RecordingBucket;
use Aws\Exception\AwsException;
use Illuminate\Database\Eloquent\Collection;
use League\Flysystem\FilesystemException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'console';

    /**
     * @return Collection<int, CallEvent>
     */
    #[Computed]
    public function events(): Collection
    {
        return CallEvent::query()->latest('id')->limit(50)->get();
    }

    public function clear(): void
    {
        CallEvent::query()->delete();
    }

    /**
     * List the recordings bucket, turning any AWS failure into a message for the panel.
     *
     * @return array{bucket: ?string, isConfigured: bool, objects: array<int, array{path: string, size: ?int, lastModified: ?int}>, error: ?string}
     */
    #[Computed]
    public function recordings(): array
    {
        $settings = VonageSetting::current();
        $listing = ['bucket' => $settings->aws_bucket, 'isConfigured' => $settings->hasRecordingBucket(), 'objects' => [], 'error' => null];

        if (! $listing['isConfigured']) {
            return $listing;
        }

        try {
            $listing['objects'] = resolve(RecordingBucket::class)->objects($settings);
        } catch (AwsException|FilesystemException $exception) {
            $awsException = $exception instanceof AwsException ? $exception : $exception->getPrevious();
            $errorCode = $awsException instanceof AwsException ? $awsException->getAwsErrorCode() : null;

            $listing['error'] = sprintf('Could not read this bucket%s. Check the AWS keys, region and bucket name in Settings.', $errorCode ? " ({$errorCode})" : '');
        }

        return $listing;
    }

    /**
     * Tailwind classes that colour a console line by its type.
     */
    public function colourFor(CallEventType $type): string
    {
        return match ($type) {
            CallEventType::Incoming => 'text-vonage-purple-light',
            CallEventType::Answerphone => 'text-amber-300',
            CallEventType::RingingBrowser => 'text-emerald-300',
            CallEventType::Outbound => 'text-sky-300',
            CallEventType::Status => 'text-white/50',
        };
    }
};
?>

<section class="flex h-full min-h-[32rem] flex-col overflow-hidden rounded-3xl bg-vonage-black text-white shadow-xl">
    <div class="flex items-center justify-between border-b border-white/10 px-6 py-3">
        <div class="flex items-center gap-1" role="tablist">
            @foreach (['console' => 'Call console', 'recordings' => 'Recordings'] as $tabName => $tabLabel)
                <button type="button" role="tab" wire:key="tab-{{ $tabName }}" wire:click="$set('tab', '{{ $tabName }}')" aria-selected="{{ $tab === $tabName ? 'true' : 'false' }}"
                    @class(['flex items-center gap-3 rounded-full px-3 py-1.5 text-sm font-semibold uppercase tracking-[0.2em] transition', 'bg-white/10 text-white' => $tab === $tabName, 'text-white/50 hover:text-white' => $tab !== $tabName])>
                    @if ($tabName === 'console')
                        <span class="relative flex size-2.5">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-vonage-purple opacity-75"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-vonage-purple"></span>
                        </span>
                    @endif
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        @if ($tab === 'console')
            <button type="button" wire:click="clear" class="rounded-full px-3 py-1 text-xs font-medium text-white/50 transition hover:bg-white/10 hover:text-white">Clear</button>
        @else
            <button type="button" wire:click="$refresh" class="rounded-full px-3 py-1 text-xs font-medium text-white/50 transition hover:bg-white/10 hover:text-white">Refresh</button>
        @endif
    </div>

    @if ($tab === 'console')
        <ol wire:key="console-log" wire:poll.2s class="flex-1 space-y-1 overflow-y-auto px-6 py-4 font-mono text-xs leading-relaxed">
            @forelse ($this->events as $event)
                <li wire:key="call-event-{{ $event->id }}" class="flex gap-3">
                    <time class="shrink-0 text-white/30" datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('H:i:s') }}</time>
                    <span class="w-24 shrink-0 font-semibold {{ $this->colourFor($event->type) }}">{{ $event->type->label() }}</span>
                    <span class="text-white/85">{{ $event->message }}</span>
                </li>
            @empty
                <li class="text-white/40">Waiting for calls… Incoming calls appear here, including those taken by the answerphone.</li>
            @endforelse
        </ol>
    @else
        <div wire:key="recordings-list" class="flex-1 overflow-y-auto px-6 py-4 font-mono text-xs leading-relaxed">
            @if (! $this->recordings['isConfigured'])
                <p class="text-white/40">Add your AWS keys, region and bucket in <a href="{{ route('settings') }}" wire:navigate class="underline hover:text-white">Settings</a> to see recordings here.</p>
            @elseif ($this->recordings['error'])
                <p class="text-red-300" role="alert">{{ $this->recordings['error'] }}</p>
            @else
                <p class="mb-3 text-white/40">
                    s3://{{ $this->recordings['bucket'] }}
                    @if (count($this->recordings['objects']) === \App\Services\RecordingBucket::MAX_OBJECTS)
                        · showing the first {{ \App\Services\RecordingBucket::MAX_OBJECTS }} objects
                    @endif
                </p>

                <ol class="space-y-1">
                    @forelse ($this->recordings['objects'] as $object)
                        <li wire:key="recording-{{ $object['path'] }}" class="flex gap-3">
                            <time class="shrink-0 text-white/30">{{ $object['lastModified'] ? \Illuminate\Support\Carbon::createFromTimestamp($object['lastModified'])->format('Y-m-d H:i') : '—' }}</time>
                            <span class="w-20 shrink-0 text-right text-white/50">{{ $object['size'] !== null ? \Illuminate\Support\Number::fileSize($object['size'], precision: 1) : '—' }}</span>
                            <span class="min-w-0 break-all text-white/85">{{ $object['path'] }}</span>
                        </li>
                    @empty
                        <li class="text-white/40">This bucket is empty.</li>
                    @endforelse
                </ol>
            @endif
        </div>
    @endif
</section>
