<?php

use App\Models\VonageSetting;
use App\Services\RecordingBucket;
use Aws\Api\DateTimeResult;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Serve the given S3 responses through the real S3 disk, without touching the network.
 *
 * @param  array<int, mixed>  $responses
 */
function fakeS3Responses(VonageSetting $settings, array $responses): MockHandler
{
    $disk = (new RecordingBucket)->disk($settings);
    $handler = new MockHandler($responses);
    $disk->getClient()->getHandlerList()->setHandler($handler);

    Storage::shouldReceive('build')->andReturn($disk);

    return $handler;
}

function s3Error(string $code): Closure
{
    return fn (CommandInterface $command) => new S3Exception($code, $command, ['code' => $code]);
}

/**
 * @param  array<int, string>  $keys
 */
function s3Listing(array $keys): Result
{
    return new Result([
        'IsTruncated' => false,
        'Contents' => array_map(fn (string $key): array => [
            'Key' => $key,
            'Size' => 2048,
            'LastModified' => new DateTimeResult('2026-10-20T09:30:00Z'),
        ], $keys),
    ]);
}

function settingsWithBucket(): VonageSetting
{
    return saveVonageSettings([...privateKeySettings(), ...answerphoneGreetingSettings(), ...recordingBucketSettings()]);
}

test('the disk is built from the saved AWS settings', function () {
    $disk = (new RecordingBucket)->disk(settingsWithBucket());

    expect($disk->getClient()->getRegion())->toBe('eu-west-2')
        ->and($disk->getClient()->getCredentials()->wait()->getAccessKeyId())->toBe('AKIAIOSFODNN7EXAMPLE')
        ->and($disk->getConfig()['bucket'])->toBe('dialler-recordings');
});

test('a listing makes one request however many objects it returns', function () {
    $settings = settingsWithBucket();
    $handler = fakeS3Responses($settings, [s3Listing(['calls/one.mp3', 'calls/two.mp3'])]);

    $objects = (new RecordingBucket)->objects($settings);

    expect($objects)->toHaveCount(2)
        ->and($objects[0]['path'])->toBe('calls/one.mp3')
        ->and($objects[0]['size'])->toBe(2048)
        ->and($objects[0]['lastModified'])->toBe(strtotime('2026-10-20T09:30:00Z'))
        ->and($handler->count())->toBe(0);
});

test('listings stop at the object limit', function () {
    $settings = settingsWithBucket();
    fakeS3Responses($settings, [s3Listing(array_map(fn (int $number): string => "calls/{$number}.mp3", range(1, RecordingBucket::MAX_OBJECTS + 5)))]);

    expect((new RecordingBucket)->objects($settings))->toHaveCount(RecordingBucket::MAX_OBJECTS);
});

test('the recordings tab lists the bucket and does not poll it', function () {
    fakeS3Responses(settingsWithBucket(), [s3Listing(['calls/one.mp3'])]);

    Livewire::test('call-console')
        ->assertSeeHtml('wire:poll')
        ->set('tab', 'recordings')
        ->assertSee('s3://dialler-recordings')
        ->assertSee('calls/one.mp3')
        ->assertSee('2.0 KB')
        ->assertDontSeeHtml('wire:poll');
});

test('the console tab does not read the bucket', function () {
    $handler = fakeS3Responses(settingsWithBucket(), [s3Listing([])]);

    Livewire::test('call-console')->assertSee('Waiting for calls');

    expect($handler->count())->toBe(1);
});

test('an empty bucket says so', function () {
    fakeS3Responses(settingsWithBucket(), [s3Listing([])]);

    Livewire::test('call-console')
        ->set('tab', 'recordings')
        ->assertSee('This bucket is empty.');
});

test('an AWS failure is shown instead of an empty bucket', function () {
    fakeS3Responses(settingsWithBucket(), [s3Error('AccessDenied')]);

    Livewire::test('call-console')
        ->set('tab', 'recordings')
        ->assertSee('Could not read this bucket (AccessDenied)')
        ->assertDontSee('This bucket is empty.');
});

test('the recordings tab prompts for AWS details when they are missing', function () {
    Livewire::test('call-console')
        ->set('tab', 'recordings')
        ->assertSee('Add your AWS keys, region and bucket');
});

test('the AWS credentials are hidden from settings serialization', function () {
    expect(json_decode((string) json_encode(settingsWithBucket()), true))
        ->toHaveKey('aws_bucket')
        ->not->toHaveKey('aws_access_key_id')
        ->not->toHaveKey('aws_secret_access_key');
});
