<?php

namespace App\Services;

use App\Models\VonageSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Updates the Vonage application the dialler is configured to use.
 */
class VonageApplicationUpdater
{
    public function __construct(private VonageTokenGenerator $tokens) {}

    /**
     * Point the application's answer and event webhooks at this app.
     *
     * Vonage replaces the application on update, so everything else is read first and sent back
     * unchanged. It is decoded as objects so that empty JSON objects are not sent back as arrays.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function pointVoiceWebhooksAtThisApp(VonageSetting $settings): void
    {
        $url = 'https://api.nexmo.com/v2/applications/'.$settings->application_id;

        $application = $this->request($settings)->get($url)->throw()->object();

        data_set($application, 'capabilities.voice.webhooks.answer_url.address', $settings->publicRoute('webhooks.answer'));
        data_set($application, 'capabilities.voice.webhooks.event_url.address', $settings->publicRoute('webhooks.event'));
        data_fill($application, 'capabilities.voice.webhooks.answer_url.http_method', 'GET');
        data_fill($application, 'capabilities.voice.webhooks.event_url.http_method', 'POST');

        $this->request($settings)
            ->put($url, Arr::only((array) $application, ['name', 'keys', 'privacy', 'capabilities']))
            ->throw();
    }

    private function request(VonageSetting $settings): PendingRequest
    {
        return Http::withToken($this->tokens->applicationToken($settings))->acceptJson();
    }
}
