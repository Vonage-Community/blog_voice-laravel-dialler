<?php

namespace App\Services;

use App\Models\VonageSetting;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Signs RS256 JWTs for the Vonage APIs and the Client SDK using the uploaded private key.
 */
class VonageTokenGenerator
{
    /**
     * ACL paths the Client SDK needs for in-browser voice calling.
     *
     * @var array<string, array{}>
     */
    private const array CLIENT_ACL_PATHS = [
        '/*/users/**' => [],
        '/*/conversations/**' => [],
        '/*/sessions/**' => [],
        '/*/devices/**' => [],
        '/*/media/**' => [],
        '/*/knocking/**' => [],
        '/*/legs/**' => [],
    ];

    /**
     * How long a token stays valid, in seconds.
     */
    private const int TTL_SECONDS = 3600;

    /**
     * Generate an application token for server-to-server Vonage API calls.
     */
    public function applicationToken(VonageSetting $settings): string
    {
        return $this->sign($settings, []);
    }

    /**
     * Generate a Client SDK token that signs the browser in as the dialler user.
     */
    public function clientToken(VonageSetting $settings): string
    {
        return $this->sign($settings, [
            'sub' => VonageSetting::DIALLER_USER,
            'acl' => ['paths' => array_map(fn (array $rule): object => (object) $rule, self::CLIENT_ACL_PATHS)],
        ]);
    }

    /**
     * Sign the given claims along with the standard Vonage claims.
     *
     * @param  array<string, mixed>  $claims
     */
    private function sign(VonageSetting $settings, array $claims): string
    {
        if (! $settings->isConfigured()) {
            throw new RuntimeException('Vonage application ID and private key must be configured first.');
        }

        $privateKey = openssl_pkey_get_private((string) $settings->private_key);

        if ($privateKey === false) {
            throw new RuntimeException('The uploaded Vonage private key could not be read.');
        }

        $issuedAt = now()->getTimestamp();

        $payload = [
            'application_id' => $settings->application_id,
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::TTL_SECONDS,
            'jti' => (string) Str::uuid(),
            ...$claims,
        ];

        $segments = [
            $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];

        openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Encode a value using the URL-safe base64 alphabet without padding.
     */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
