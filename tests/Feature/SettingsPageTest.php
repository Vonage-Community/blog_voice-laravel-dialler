<?php

use App\Models\VonageSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function fakePrivateKeyUpload(): UploadedFile
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);

    return UploadedFile::fake()->createWithContent('private.key', $pem);
}

test('the settings page shows the webhook URLs', function () {
    $this->get(route('settings'))
        ->assertOk()
        ->assertSee(route('webhooks.answer'))
        ->assertSee(route('webhooks.event'));
});

test('settings are saved and the dialler user is provisioned', function () {
    Storage::fake('local');
    Http::fake(['api.nexmo.com/v1/users' => Http::response(['id' => 'USR-1'], 201)]);

    Livewire::test('pages::settings')
        ->set('applicationId', '0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->set('vonageNumber', '+44 7700 900000')
        ->set('privateKey', fakePrivateKeyUpload())
        ->set('greeting', UploadedFile::fake()->create('welcome.mp3', 50, 'audio/mpeg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('statusIsWarning', false);

    $settings = VonageSetting::current();

    expect($settings->application_id)->toBe('0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->and($settings->vonage_number)->toBe('447700900000')
        ->and($settings->isConfigured())->toBeTrue()
        ->and($settings->answerphone_filename)->toBe('welcome.mp3');

    Storage::disk('local')->assertExists('answerphone/greeting.mp3');
    Http::assertSent(fn ($request) => $request['name'] === VonageSetting::DIALLER_USER && $request->hasHeader('Authorization'));
});

test('an existing dialler user is treated as success', function () {
    Http::fake(['api.nexmo.com/v1/users' => Http::response([], 409)]);

    Livewire::test('pages::settings')
        ->set('applicationId', '0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->set('privateKey', fakePrivateKeyUpload())
        ->call('save')
        ->assertSet('statusIsWarning', false);
});

test('a warning is shown when Vonage rejects the credentials', function () {
    Http::fake(['api.nexmo.com/v1/users' => Http::response([], 401)]);

    Livewire::test('pages::settings')
        ->set('applicationId', '0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->set('privateKey', fakePrivateKeyUpload())
        ->call('save')
        ->assertSet('statusIsWarning', true);
});

test('an invalid private key is rejected', function () {
    Http::fake();

    Livewire::test('pages::settings')
        ->set('applicationId', '0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->set('privateKey', UploadedFile::fake()->createWithContent('private.key', 'not a key'))
        ->call('save')
        ->assertHasErrors('privateKey');

    expect(VonageSetting::current()->private_key)->toBeNull();
    Http::assertNothingSent();
});

test('the application ID and a private key are required', function () {
    Livewire::test('pages::settings')
        ->set('applicationId', 'not-a-uuid')
        ->call('save')
        ->assertHasErrors(['applicationId' => 'uuid', 'privateKey' => 'required']);
});

test('the greeting must be an mp3', function () {
    Livewire::test('pages::settings')
        ->set('greeting', UploadedFile::fake()->create('greeting.wav', 50, 'audio/wav'))
        ->call('save')
        ->assertHasErrors('greeting');
});

test('AWS details are saved and the secret never returns to the form', function () {
    Http::fake(['api.nexmo.com/v1/users' => Http::response(['id' => 'USR-1'], 201)]);

    Livewire::test('pages::settings')
        ->set('applicationId', '0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->set('privateKey', fakePrivateKeyUpload())
        ->set('awsAccessKeyId', 'AKIAIOSFODNN7EXAMPLE')
        ->set('awsSecretAccessKey', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
        ->set('awsRegion', 'eu-west-2')
        ->set('awsBucket', 'dialler-recordings')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('awsSecretAccessKey', '');

    expect(VonageSetting::current()->hasRecordingBucket())->toBeTrue();

    Livewire::test('pages::settings')
        ->assertSet('awsBucket', 'dialler-recordings')
        ->assertSet('awsSecretAccessKey', '')
        ->assertDontSee('wJalrXUtnFEMI')
        ->call('save')
        ->assertHasNoErrors();

    expect(VonageSetting::current()->aws_secret_access_key)->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
});

test('the Vonage number must be in international format', function () {
    Livewire::test('pages::settings')
        ->set('vonageNumber', '07700 900000')
        ->call('save')
        ->assertHasErrors(['vonageNumber' => 'regex'])
        ->assertSee('no leading zero, like 447700900000');
});

test('webhook addresses Vonage cannot reach are flagged', function (string $host, bool $isFlagged) {
    $response = $this->get("https://{$host}/settings")->assertOk();

    $isFlagged
        ? $response->assertSee('Vonage can')
        : $response->assertDontSee('Vonage can');
})->with([
    'herd site' => ['vonage-laravel-dialler.test', true],
    'localhost' => ['localhost', true],
    'public tunnel' => ['dialler.example.com', false],
]);

test('the tunnel address replaces the local webhook addresses while it is running', function () {
    VonageSetting::current()->update(['public_url' => 'https://abc123.ngrok-free.app']);

    $this->get(route('settings'))
        ->assertOk()
        ->assertSee('https://abc123.ngrok-free.app/webhooks/answer')
        ->assertSee('https://abc123.ngrok-free.app/webhooks/event')
        ->assertDontSee('Vonage can');
});
