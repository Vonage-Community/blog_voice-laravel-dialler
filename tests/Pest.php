<?php

use App\Models\VonageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Save Vonage settings for a test, starting from an application ID and a Vonage number.
 *
 * @param  array<string, string|bool|null>  $attributes
 */
function saveVonageSettings(array $attributes = []): VonageSetting
{
    $settings = VonageSetting::current();
    $settings->update([
        'application_id' => fake()->uuid(),
        'vonage_number' => fake()->numerify('4479########'),
        ...$attributes,
    ]);

    return $settings;
}

/**
 * Settings holding a freshly generated RSA private key.
 *
 * @return array{private_key: string}
 */
function privateKeySettings(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    if ($key === false) {
        throw new RuntimeException('Unable to generate a test RSA key.');
    }

    openssl_pkey_export($key, $pem);

    return ['private_key' => $pem];
}

/**
 * Settings for an uploaded answerphone greeting.
 *
 * @return array{answerphone_path: string, answerphone_filename: string}
 */
function answerphoneGreetingSettings(): array
{
    return ['answerphone_path' => 'answerphone/greeting.mp3', 'answerphone_filename' => 'greeting.mp3'];
}

/**
 * Settings for the AWS keys, region and recordings bucket.
 *
 * @return array{aws_access_key_id: string, aws_secret_access_key: string, aws_region: string, aws_bucket: string}
 */
function recordingBucketSettings(): array
{
    return [
        'aws_access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
        'aws_secret_access_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        'aws_region' => 'eu-west-2',
        'aws_bucket' => 'dialler-recordings',
    ];
}
