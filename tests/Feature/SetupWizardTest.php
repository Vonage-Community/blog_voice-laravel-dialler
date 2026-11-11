<?php

use App\Models\VonageSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function setupWizardPrivateKey(): UploadedFile
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);

    return UploadedFile::fake()->createWithContent('private.key', $pem);
}

test('each modal saves its settings and the last one opens the dialler', function () {
    Storage::fake('local');
    Http::fake(['api.nexmo.com/v1/users' => Http::response(['id' => 'USR-1'], 201)]);

    Livewire::test('setup-wizard')
        ->assertSet('step', 'applicationId')
        ->set('applicationId', '0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'privateKey')
        ->set('privateKey', setupWizardPrivateKey())
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'vonageNumber')
        ->set('vonageNumber', '+44 7700 900000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'greeting')
        ->set('greeting', UploadedFile::fake()->create('welcome.mp3', 50, 'audio/mpeg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'aws')
        ->assertNoRedirect()
        ->set('awsAccessKeyId', 'AKIAIOSFODNN7EXAMPLE')
        ->set('awsSecretAccessKey', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
        ->set('awsRegion', 'eu-west-2')
        ->set('awsBucket', 'dialler-recordings')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('awsSecretAccessKey', '')
        ->assertRedirect(route('phone'));

    $settings = VonageSetting::current();

    expect($settings->isReadyToDial())->toBeTrue()
        ->and($settings->application_id)->toBe('0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->and($settings->vonage_number)->toBe('447700900000')
        ->and($settings->answerphone_filename)->toBe('welcome.mp3')
        ->and($settings->aws_access_key_id)->toBe('AKIAIOSFODNN7EXAMPLE')
        ->and($settings->aws_secret_access_key)->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
        ->and($settings->aws_region)->toBe('eu-west-2')
        ->and($settings->aws_bucket)->toBe('dialler-recordings');

    Storage::disk('local')->assertExists('answerphone/greeting.mp3');
    Http::assertSentCount(1);
});

test('the application ID modal cannot be skipped', function () {
    Livewire::test('setup-wizard')
        ->call('save')
        ->assertHasErrors(['applicationId' => 'required'])
        ->assertSet('step', 'applicationId');
});

test('the private key modal cannot be skipped', function () {
    VonageSetting::current()->update(['application_id' => '0f4a1c2e-1234-4abc-9def-0123456789ab']);

    Livewire::test('setup-wizard')
        ->assertSet('step', 'privateKey')
        ->call('save')
        ->assertHasErrors(['privateKey' => 'required'])
        ->assertSet('step', 'privateKey');
});

test('the number and greeting modals cannot be skipped', function () {
    saveVonageSettings([...privateKeySettings(), 'vonage_number' => null]);

    Livewire::test('setup-wizard')
        ->assertSet('step', 'vonageNumber')
        ->call('save')
        ->assertHasErrors(['vonageNumber' => 'required'])
        ->set('vonageNumber', '447700900000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'greeting')
        ->call('save')
        ->assertHasErrors(['greeting' => 'required'])
        ->assertNoRedirect();
});

test('the wizard opens on the first setting still missing', function () {
    saveVonageSettings(privateKeySettings());

    Livewire::test('setup-wizard')->assertSet('step', 'greeting');
});

test('an invalid private key keeps the modal open', function () {
    Http::fake();
    VonageSetting::current()->update(['application_id' => '0f4a1c2e-1234-4abc-9def-0123456789ab']);

    Livewire::test('setup-wizard')
        ->set('privateKey', UploadedFile::fake()->createWithContent('private.key', 'not a key'))
        ->call('save')
        ->assertHasErrors('privateKey')
        ->assertSee('That file is not a valid private key')
        ->assertSet('step', 'privateKey');

    expect(VonageSetting::current()->private_key)->toBeNull();
    Http::assertNothingSent();
});

test('rejected credentials warn on the next modal and can be corrected by going back', function () {
    Http::fake(['api.nexmo.com/v1/users' => Http::response([], 401)]);
    VonageSetting::current()->update(['application_id' => '0f4a1c2e-1234-4abc-9def-0123456789ab']);

    Livewire::test('setup-wizard')
        ->set('privateKey', setupWizardPrivateKey())
        ->call('save')
        ->assertSet('statusIsWarning', true)
        ->assertSee('Vonage rejected the request')
        ->assertSet('step', 'vonageNumber')
        ->call('back')
        ->assertSet('step', 'privateKey')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'vonageNumber');
});

test('the AWS modal cannot be skipped', function () {
    saveVonageSettings([...privateKeySettings(), ...answerphoneGreetingSettings()]);

    Livewire::test('setup-wizard')
        ->assertSet('step', 'aws')
        ->call('save')
        ->assertHasErrors([
            'awsAccessKeyId' => 'required',
            'awsSecretAccessKey' => 'required',
            'awsRegion' => 'required',
            'awsBucket' => 'required',
        ])
        ->assertSee('The S3 bucket field is required')
        ->assertNoRedirect();
});

test('malformed AWS details are rejected', function () {
    saveVonageSettings([...privateKeySettings(), ...answerphoneGreetingSettings()]);

    Livewire::test('setup-wizard')
        ->set('awsAccessKeyId', 'not a key')
        ->set('awsSecretAccessKey', 'secret')
        ->set('awsRegion', 'London')
        ->set('awsBucket', 'Not_A_Bucket')
        ->call('save')
        ->assertHasErrors(['awsAccessKeyId', 'awsRegion', 'awsBucket'])
        ->assertHasNoErrors('awsSecretAccessKey');

    expect(VonageSetting::current()->hasRecordingBucket())->toBeFalse();
});

test('the saved AWS secret is kept out of the form and can be left unchanged', function () {
    saveVonageSettings([...privateKeySettings(), ...recordingBucketSettings()]);

    Livewire::test('setup-wizard')
        ->assertSet('awsAccessKeyId', 'AKIAIOSFODNN7EXAMPLE')
        ->assertSet('awsSecretAccessKey', '')
        ->assertDontSee('wJalrXUtnFEMI')
        ->call('back')
        ->assertSet('step', 'vonageNumber')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'greeting')
        ->set('greeting', UploadedFile::fake()->create('welcome.mp3', 50, 'audio/mpeg'))
        ->call('save')
        ->assertRedirect(route('phone'));

    expect(VonageSetting::current()->aws_secret_access_key)->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
});
