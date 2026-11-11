<?php

namespace App\Services;

use App\Models\VonageSetting;
use Illuminate\Support\Facades\Http;

/**
 * Makes sure the Vonage user the browser phone signs in as exists on the application.
 */
class VonageUserProvisioner
{
    public function __construct(private VonageTokenGenerator $tokens) {}

    /**
     * Create the dialler user, treating "already exists" as success.
     */
    public function ensureDiallerUserExists(VonageSetting $settings): bool
    {
        $response = Http::withToken($this->tokens->applicationToken($settings))
            ->acceptJson()
            ->post('https://api.nexmo.com/v1/users', [
                'name' => VonageSetting::DIALLER_USER,
                'display_name' => 'Vonage Dialler',
            ]);

        return $response->successful() || $response->status() === 409;
    }
}
