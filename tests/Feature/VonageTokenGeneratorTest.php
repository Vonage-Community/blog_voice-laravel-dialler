<?php

use App\Models\VonageSetting;
use App\Services\VonageTokenGenerator;

/**
 * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: bool}
 */
function decodeVonageToken(string $token, string $privateKeyPem): array
{
    [$header, $payload, $signature] = explode('.', $token);
    $decode = fn (string $segment): string => base64_decode(strtr($segment, '-_', '+/'));
    $publicKey = openssl_pkey_get_details(openssl_pkey_get_private($privateKeyPem))['key'];

    return [
        json_decode($decode($header), true),
        json_decode($decode($payload), true),
        openssl_verify("{$header}.{$payload}", $decode($signature), $publicKey, OPENSSL_ALGO_SHA256) === 1,
    ];
}

test('client tokens are RS256 signed for the dialler user', function () {
    $settings = saveVonageSettings(privateKeySettings());

    [$header, $payload, $isValid] = decodeVonageToken(app(VonageTokenGenerator::class)->clientToken($settings), $settings->private_key);

    expect($isValid)->toBeTrue()
        ->and($header['alg'])->toBe('RS256')
        ->and($payload['application_id'])->toBe($settings->application_id)
        ->and($payload['sub'])->toBe(VonageSetting::DIALLER_USER)
        ->and($payload['acl']['paths'])->toHaveKey('/*/sessions/**')
        ->and($payload['exp'] - $payload['iat'])->toBe(3600);
});

test('application tokens carry no user subject', function () {
    $settings = saveVonageSettings(privateKeySettings());

    [, $payload, $isValid] = decodeVonageToken(app(VonageTokenGenerator::class)->applicationToken($settings), $settings->private_key);

    expect($isValid)->toBeTrue()->and($payload)->not->toHaveKey('sub');
});

test('tokens cannot be generated before configuration', function () {
    app(VonageTokenGenerator::class)->clientToken(VonageSetting::current());
})->throws(RuntimeException::class);
