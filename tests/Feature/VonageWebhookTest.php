<?php

use App\Enums\CallEventType;
use App\Models\CallEvent;
use App\Models\VonageSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('inbound calls ring the browser phone when the answerphone is off', function () {
    saveVonageSettings();

    $this->postJson(route('webhooks.answer'), ['uuid' => 'call-1', 'from' => '447700900001', 'to' => '447700900000'])
        ->assertOk()
        ->assertExactJson([[
            'action' => 'connect',
            'from' => '447700900001',
            'endpoint' => [['type' => 'app', 'user' => VonageSetting::DIALLER_USER]],
        ]]);

    expect(CallEvent::pluck('type')->all())->toBe([CallEventType::Incoming, CallEventType::RingingBrowser]);
});

test('inbound calls stream the uploaded greeting when the answerphone is on', function () {
    $settings = saveVonageSettings([...answerphoneGreetingSettings(), 'answerphone_enabled' => true]);

    $response = $this->getJson(route('webhooks.answer', ['uuid' => 'call-2', 'from' => '447700900001', 'to' => '447700900000']))
        ->assertOk();

    expect($response->json('0.action'))->toBe('stream')
        ->and($response->json('0.streamUrl.0'))->toStartWith(route('answerphone.audio'));

    expect(CallEvent::pluck('type')->all())->toBe([CallEventType::Incoming, CallEventType::Answerphone])
        ->and(CallEvent::first()->message)->toBe('Incoming call from 447700900001');
});

test('the answerphone speaks a fallback message when no greeting is uploaded', function () {
    saveVonageSettings(['answerphone_enabled' => true]);

    $this->postJson(route('webhooks.answer'), ['uuid' => 'call-3', 'from' => '447700900001'])
        ->assertOk()
        ->assertJsonPath('0.action', 'talk');
});

test('calls from the browser phone connect to the dialled number', function () {
    saveVonageSettings(['vonage_number' => '447700900000']);

    $this->postJson(route('webhooks.answer'), ['uuid' => 'call-4', 'from_user' => VonageSetting::DIALLER_USER, 'to' => '+44 7700 900123'])
        ->assertOk()
        ->assertExactJson([[
            'action' => 'connect',
            'from' => '447700900000',
            'endpoint' => [['type' => 'phone', 'number' => '447700900123']],
        ]]);

    expect(CallEvent::sole()->type)->toBe(CallEventType::Outbound);
});

test('call status events are logged to the console', function () {
    $this->postJson(route('webhooks.event'), ['uuid' => 'call-5', 'status' => 'completed'])->assertNoContent();
    $this->postJson(route('webhooks.event'), ['uuid' => 'call-5'])->assertNoContent();

    expect(CallEvent::sole())
        ->type->toBe(CallEventType::Status)
        ->message->toBe('Call completed');
});

test('the greeting is served for Vonage to stream', function () {
    Storage::fake('local');
    Storage::disk('local')->putFileAs('answerphone', UploadedFile::fake()->create('greeting.mp3', 10, 'audio/mpeg'), 'greeting.mp3');
    saveVonageSettings(answerphoneGreetingSettings());

    $this->get(route('answerphone.audio'))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg');
});

test('the greeting route returns not found without an upload', function () {
    $this->get(route('answerphone.audio'))->assertNotFound();
});

test('the greeting is streamed from the tunnel address while it is running', function () {
    saveVonageSettings([...answerphoneGreetingSettings(), 'answerphone_enabled' => true, 'public_url' => 'https://abc123.ngrok-free.app']);

    $response = $this->getJson(route('webhooks.answer', ['uuid' => 'call-9', 'from' => '447700900001']))->assertOk();

    expect($response->json('0.streamUrl.0'))->toStartWith('https://abc123.ngrok-free.app/answerphone/greeting.mp3');
});
