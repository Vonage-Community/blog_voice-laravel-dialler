<?php

use App\Models\VonageSetting;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

const TUNNEL_URL = 'https://abc123.ngrok-free.app';

/**
 * @return array<string, mixed>
 */
function vonageApplication(): array
{
    return [
        'id' => 'ignored',
        'name' => 'My dialler',
        'keys' => ['public_key' => 'PUBLIC KEY'],
        'privacy' => ['improve_ai' => false],
        'capabilities' => [
            'voice' => [
                'signed_callbacks' => true,
                'webhooks' => [
                    'answer_url' => ['address' => 'http://example.com/answer', 'http_method' => 'GET', 'socket_timeout' => 5000],
                    'event_url' => ['address' => 'http://example.com/event', 'http_method' => 'GET', 'socket_timeout' => 10000],
                    'fallback_answer_url' => ['address' => 'http://example.com/fallback', 'http_method' => 'GET'],
                ],
            ],
            'rtc' => ['webhooks' => new stdClass],
        ],
        '_links' => ['self' => ['href' => '/v2/applications/ignored']],
    ];
}

function fakeNgrokProcess(int $iterations = 3): void
{
    Process::fake(['*ngrok*' => Process::describe()->output('lvl=info msg="started tunnel"')->iterations($iterations)]);
}

/**
 * Fake ngrok's local API, which is down until the agent starts, and Vonage's Applications API.
 *
 * @param  array<string, mixed>|null  $vonageUpdate
 */
function fakeTunnelApis(int $vonageUpdateStatus = 200, ?array $vonageUpdate = []): void
{
    Http::fake([
        '127.0.0.1:4040/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(['tunnels' => [['public_url' => TUNNEL_URL, 'proto' => 'https']]]),
        'api.nexmo.com/*' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(vonageApplication())
            : Http::response($vonageUpdate, $vonageUpdateStatus),
    ]);
}

beforeEach(function () {
    Process::preventStrayProcesses();
    Sleep::fake();
    Storage::fake('local');
});

function configuredSettings(): VonageSetting
{
    return saveVonageSettings(privateKeySettings());
}

test('it starts ngrok, then points the voice webhooks at the public URL', function () {
    $settings = configuredSettings();
    fakeNgrokProcess();
    fakeTunnelApis();

    $this->artisan('vonage:tunnel')
        ->expectsOutputToContain('Forwarding '.TUNNEL_URL)
        ->expectsOutputToContain(TUNNEL_URL.'/webhooks/answer')
        ->assertFailed();

    Process::assertRan(fn ($process): bool => array_slice((array) $process->command, 0, 3) === ['ngrok', 'http', 'https://vonage-laravel-dialler.test']);

    Http::assertSent(function (Request $request) use ($settings): bool {
        if ($request->method() !== 'PUT') {
            return false;
        }

        $webhooks = json_decode($request->body(), true)['capabilities']['voice']['webhooks'];

        expect($request->url())->toBe('https://api.nexmo.com/v2/applications/'.$settings->application_id)
            ->and($webhooks['answer_url']['address'])->toBe(TUNNEL_URL.'/webhooks/answer')
            ->and($webhooks['event_url']['address'])->toBe(TUNNEL_URL.'/webhooks/event');

        return true;
    });
});

test('everything else on the Vonage application is sent back unchanged', function () {
    configuredSettings();
    fakeNgrokProcess();
    fakeTunnelApis();

    $this->artisan('vonage:tunnel');

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PUT') {
            return false;
        }

        $application = json_decode($request->body(), true);
        $webhooks = $application['capabilities']['voice']['webhooks'];

        expect($application['name'])->toBe('My dialler')
            ->and($application['keys'])->toBe(['public_key' => 'PUBLIC KEY'])
            ->and($application['privacy'])->toBe(['improve_ai' => false])
            ->and($application['capabilities']['voice']['signed_callbacks'])->toBeTrue()
            ->and($webhooks['answer_url']['socket_timeout'])->toBe(5000)
            ->and($webhooks['event_url']['http_method'])->toBe('GET')
            ->and($webhooks['fallback_answer_url']['address'])->toBe('http://example.com/fallback')
            ->and($request->body())->toContain('"rtc":{"webhooks":{}}')
            ->and($application)->not->toHaveKeys(['id', '_links']);

        return true;
    });
});

test('the public URL is saved while the tunnel runs and cleared when it ends', function () {
    configuredSettings();
    fakeNgrokProcess();

    $savedDuringRun = null;

    Http::fake([
        '127.0.0.1:4040/*' => Http::sequence()->pushFailedConnection()->push(['tunnels' => [['public_url' => TUNNEL_URL]]]),
        'api.nexmo.com/*' => function () use (&$savedDuringRun) {
            $savedDuringRun = VonageSetting::current()->public_url;

            return Http::response(vonageApplication());
        },
    ]);

    $this->artisan('vonage:tunnel');

    expect($savedDuringRun)->toBe(TUNNEL_URL)
        ->and(VonageSetting::current()->public_url)->toBeNull();
});

test('only the paths Vonage needs are exposed and the Host header is rewritten', function () {
    configuredSettings();
    fakeNgrokProcess();
    fakeTunnelApis();

    $this->artisan('vonage:tunnel', ['--upstream' => 'https://custom-site.test']);

    $policy = json_decode(Storage::disk('local')->get('ngrok-traffic-policy.json'), true)['on_http_request'];

    expect($policy[0]['expressions'][0])->toContain("startsWith('/webhooks/')")->toContain("startsWith('/answerphone/')")
        ->and($policy[0]['actions'][0]['type'])->toBe('deny')
        ->and($policy[1]['actions'][0]['config']['headers']['host'])->toBe('custom-site.test');
});

test('it stops if the Vonage credentials are missing', function () {
    fakeNgrokProcess();
    Http::fake();

    $this->artisan('vonage:tunnel')
        ->expectsOutputToContain('application ID and private key')
        ->assertFailed();

    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('it will not start a second ngrok agent', function () {
    configuredSettings();
    fakeNgrokProcess();
    Http::fake(['127.0.0.1:4040/*' => Http::response(['tunnels' => []])]);

    $this->artisan('vonage:tunnel')
        ->expectsOutputToContain('already running')
        ->assertFailed();

    Process::assertNothingRan();
});

test('it reports the ngrok error when no tunnel starts', function () {
    configuredSettings();
    Process::fake(['*ngrok*' => Process::describe()->errorOutput('ERR_NGROK_4018 authentication failed')->exitCode(1)->iterations(0)]);
    Http::fake(['127.0.0.1:4040/*' => Http::failedConnection(), 'api.nexmo.com/*' => Http::response()]);

    $this->artisan('vonage:tunnel')
        ->expectsOutputToContain('ERR_NGROK_4018')
        ->assertFailed();

    expect(VonageSetting::current()->public_url)->toBeNull();
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'nexmo'));
});

test('the tunnel stays up with manual instructions when Vonage refuses the update', function () {
    configuredSettings();
    fakeNgrokProcess();
    fakeTunnelApis(401, ['title' => 'Unauthorized']);

    $this->artisan('vonage:tunnel')
        ->expectsOutputToContain('HTTP 401: Unauthorized')
        ->expectsOutputToContain(TUNNEL_URL.'/webhooks/event');
});
