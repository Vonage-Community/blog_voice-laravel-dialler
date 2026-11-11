<?php

namespace App\Console\Commands;

use App\Models\VonageSetting;
use App\Services\VonageApplicationUpdater;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

#[Signature('vonage:tunnel {--upstream= : The local site to expose. Defaults to this project\'s Herd site}')]
#[Description('Start an ngrok tunnel, save its public URL and point the Vonage application\'s voice webhooks at it')]
class StartVonageTunnel extends Command
{
    /**
     * The local API the ngrok agent uses to report its tunnels.
     */
    private const string NGROK_API = 'http://127.0.0.1:4040/api/tunnels';

    /**
     * How many times to check for the tunnel, half a second apart, before giving up.
     */
    private const int STARTUP_ATTEMPTS = 30;

    private bool $shouldKeepRunning = true;

    /**
     * Execute the console command.
     */
    public function handle(VonageApplicationUpdater $updater): int
    {
        $settings = VonageSetting::current();

        if (! $settings->isConfigured()) {
            $this->components->error('Add your Vonage application ID and private key in Settings first.');

            return self::FAILURE;
        }

        if ($this->ngrokApiResponse() !== null) {
            $this->components->error('ngrok is already running on this computer. Stop it, then run this command again.');

            return self::FAILURE;
        }

        $this->trap([SIGINT, SIGTERM], fn () => $this->shouldKeepRunning = false);

        $upstream = $this->upstream();
        $process = Process::forever()->start($this->ngrokCommand($upstream));

        $publicUrl = $this->waitForPublicUrl($process);

        if ($publicUrl === null) {
            $process->signal(SIGTERM);
            $this->components->error('ngrok did not start a tunnel. '.$this->lastOutput($process));

            return self::FAILURE;
        }

        $settings->update(['public_url' => $publicUrl]);

        $this->components->info("Forwarding {$publicUrl} to {$upstream}");
        $this->pushWebhooks($updater, $settings);
        $this->line('  Only the webhook and greeting paths are public. Press Ctrl+C to stop the tunnel.');

        while ($this->shouldKeepRunning && $process->running()) {
            Sleep::for(1)->second();
        }

        $tunnelDied = $this->shouldKeepRunning;

        if ($process->running()) {
            $process->signal(SIGTERM);
        }

        $settings->update(['public_url' => null]);

        if ($tunnelDied) {
            $this->components->error('ngrok stopped unexpectedly. '.$this->lastOutput($process));

            return self::FAILURE;
        }

        $this->components->info('Tunnel stopped. Your Vonage application still points at the old address until you run this again.');

        return self::SUCCESS;
    }

    /**
     * The site to expose, which is this project's Herd site unless told otherwise.
     */
    private function upstream(): string
    {
        return $this->option('upstream') ?: 'https://'.Str::slug(basename(base_path())).'.test';
    }

    /**
     * @return array<int, string>
     */
    private function ngrokCommand(string $upstream): array
    {
        return ['ngrok', 'http', $upstream, '--traffic-policy-file='.$this->writeTrafficPolicy($upstream), '--log=stdout'];
    }

    /**
     * Write the rules ngrok applies to public traffic and return the file's path.
     *
     * The app has no login, so only the paths Vonage needs are exposed. The Host header is rewritten
     * because Herd picks the site to serve from it.
     */
    private function writeTrafficPolicy(string $upstream): string
    {
        $path = 'ngrok-traffic-policy.json';

        Storage::disk('local')->put($path, (string) json_encode([
            'on_http_request' => [
                [
                    'expressions' => ["!(req.url.path.startsWith('/webhooks/') || req.url.path.startsWith('/answerphone/'))"],
                    'actions' => [['type' => 'deny', 'config' => ['status_code' => 404]]],
                ],
                [
                    'actions' => [['type' => 'add-headers', 'config' => ['headers' => ['host' => (string) parse_url($upstream, PHP_URL_HOST)]]]],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Storage::disk('local')->path($path);
    }

    private function waitForPublicUrl(InvokedProcess $process): ?string
    {
        for ($attempt = 0; $attempt < self::STARTUP_ATTEMPTS && $this->shouldKeepRunning && $process->running(); $attempt++) {
            $tunnels = $this->ngrokApiResponse()?->json('tunnels') ?? [];
            $publicUrl = collect($tunnels)->pluck('public_url')->first(fn (string $url): bool => str_starts_with($url, 'https://'));

            if ($publicUrl !== null) {
                return $publicUrl;
            }

            Sleep::for(500)->milliseconds();
        }

        return null;
    }

    private function ngrokApiResponse(): ?Response
    {
        try {
            return Http::timeout(2)->acceptJson()->get(self::NGROK_API);
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Update Vonage, leaving the tunnel up with the addresses to enter by hand if that fails.
     */
    private function pushWebhooks(VonageApplicationUpdater $updater, VonageSetting $settings): void
    {
        try {
            $updater->pointVoiceWebhooksAtThisApp($settings);

            $this->components->info('Updated the voice webhooks on Vonage application '.$settings->application_id);
        } catch (ConnectionException|RequestException $exception) {
            $reason = $exception instanceof RequestException
                ? "HTTP {$exception->response->status()}: ".($exception->response->json('detail') ?? $exception->response->json('title') ?? 'no details given')
                : 'could not connect';

            $this->components->warn("Vonage did not accept the update ({$reason}). Set these on the Voice capability in the Vonage dashboard:");
        }

        $this->line('  Answer URL: '.$settings->publicRoute('webhooks.answer'));
        $this->line('  Event URL:  '.$settings->publicRoute('webhooks.event'));
    }

    private function lastOutput(InvokedProcess $process): string
    {
        return Str::limit(trim($process->errorOutput() ?: $process->output()), 1000, preserveWords: true);
    }
}
