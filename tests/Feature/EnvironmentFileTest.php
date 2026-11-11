<?php

use App\Models\VonageSetting;
use App\Services\EnvironmentFile;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

test('values replace their existing line and new keys are added to the end', function () {
    File::put(app()->environmentFilePath(), "APP_NAME=Dialler\nVONAGE_NUMBER=447700900000\nLOG_LEVEL=debug\n");

    app(EnvironmentFile::class)->write(['VONAGE_NUMBER' => '447700900111', 'VONAGE_ANSWERPHONE_ENABLED' => true, 'VONAGE_PUBLIC_URL' => null]);

    expect(File::get(app()->environmentFilePath()))->toBe(
        "APP_NAME=Dialler\nVONAGE_NUMBER=\"447700900111\"\nLOG_LEVEL=debug\nVONAGE_ANSWERPHONE_ENABLED=true\nVONAGE_PUBLIC_URL=\n"
    );
});

test('multi-line and special characters read back exactly as written', function () {
    $privateKey = privateKeySettings()['private_key'];
    $awkward = 'q"uote \back $HOME ${APP_KEY} #hash';

    app(EnvironmentFile::class)->write(['VONAGE_PRIVATE_KEY' => $privateKey, 'AWKWARD' => $awkward]);

    $parsed = Dotenv::parse(File::get(app()->environmentFilePath()));

    expect($parsed['VONAGE_PRIVATE_KEY'])->toBe($privateKey)
        ->and($parsed['AWKWARD'])->toBe($awkward)
        ->and(File::get(app()->environmentFilePath()))->toStartWith('VONAGE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n');
});

test('saved settings go to the .env file and apply straight away', function () {
    $settings = saveVonageSettings([...privateKeySettings(), ...recordingBucketSettings()]);

    $parsed = Dotenv::parse(File::get(app()->environmentFilePath()));

    expect($parsed['VONAGE_APPLICATION_ID'])->toBe($settings->application_id)
        ->and($parsed['VONAGE_PRIVATE_KEY'])->toBe($settings->private_key)
        ->and($parsed['RECORDINGS_AWS_SECRET_ACCESS_KEY'])->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
        ->and(VonageSetting::current()->hasRecordingBucket())->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasTable('vonage_settings'))->toBeFalse();
});

test('saving only writes the settings that changed', function () {
    $staleSettings = saveVonageSettings();

    VonageSetting::current()->update(['answerphone_enabled' => true]);
    $staleSettings->update(['public_url' => 'https://abc123.ngrok-free.app']);

    expect(VonageSetting::current())
        ->answerphone_enabled->toBeTrue()
        ->public_url->toBe('https://abc123.ngrok-free.app');
});
