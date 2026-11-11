<?php

namespace App\Services;

use App\Models\VonageSetting;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Form state and save behaviour shared by the Livewire components that edit the Vonage settings.
 */
trait ManagesVonageSettings
{
    use WithFileUploads;

    public string $applicationId = '';

    public string $vonageNumber = '';

    public ?TemporaryUploadedFile $privateKey = null;

    public ?TemporaryUploadedFile $greeting = null;

    public string $awsAccessKeyId = '';

    /**
     * Write-only: never filled from the saved settings, because public properties reach the browser.
     */
    public string $awsSecretAccessKey = '';

    public string $awsRegion = '';

    public string $awsBucket = '';

    public ?string $statusMessage = null;

    public bool $statusIsWarning = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'applicationId' => ['required', 'uuid'],
            'vonageNumber' => ['nullable', 'regex:/^\+?[1-9][0-9 ]{5,19}$/'],
            'privateKey' => [VonageSetting::current()->private_key ? 'nullable' : 'required', 'file', 'max:16'],
            'greeting' => ['nullable', 'file', 'extensions:mp3', 'mimes:mp3,mpga', 'max:10240'],
            'awsAccessKeyId' => ['nullable', 'regex:/^[A-Z0-9]{16,128}$/'],
            'awsSecretAccessKey' => ['nullable', 'string', 'max:128'],
            'awsRegion' => ['nullable', 'regex:/^[a-z]{2}(-[a-z]+)+-[0-9]+$/'],
            'awsBucket' => ['nullable', 'regex:/^[a-z0-9][a-z0-9.\-]{1,61}[a-z0-9]$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'applicationId' => 'application ID',
            'vonageNumber' => 'Vonage number',
            'privateKey' => 'private key',
            'greeting' => 'answerphone greeting',
            'awsAccessKeyId' => 'AWS access key ID',
            'awsSecretAccessKey' => 'AWS secret access key',
            'awsRegion' => 'AWS region',
            'awsBucket' => 'S3 bucket',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'vonageNumber.regex' => 'Enter the number in international format, with its country code and no leading zero, like 447700900000.',
        ];
    }

    /**
     * Fill the form from the saved settings, leaving the secret access key out.
     */
    private function fillFromSettings(VonageSetting $settings): void
    {
        $this->applicationId = (string) $settings->application_id;
        $this->vonageNumber = (string) $settings->vonage_number;
        $this->awsAccessKeyId = (string) $settings->aws_access_key_id;
        $this->awsRegion = (string) $settings->aws_region;
        $this->awsBucket = (string) $settings->aws_bucket;
    }

    /**
     * Strip the entered number down to the digits Vonage expects.
     */
    private function normalisedVonageNumber(): ?string
    {
        return preg_replace('/[^0-9]/', '', $this->vonageNumber) ?: null;
    }

    /**
     * Put the uploaded private key on the settings, adding an error if it cannot be read.
     */
    private function applyPrivateKey(VonageSetting $settings): bool
    {
        if (! $this->privateKey) {
            return true;
        }

        $pem = (string) file_get_contents($this->privateKey->getRealPath());

        if (openssl_pkey_get_private($pem) === false) {
            $this->addError('privateKey', 'That file is not a valid private key. Upload the private.key downloaded from the Vonage dashboard.');

            return false;
        }

        $settings->private_key = $pem;

        return true;
    }

    /**
     * Store the uploaded answerphone greeting against the settings.
     */
    private function applyGreeting(VonageSetting $settings): void
    {
        if ($this->greeting) {
            $settings->answerphone_path = $this->greeting->storeAs('answerphone', 'greeting.mp3', 'local');
            $settings->answerphone_filename = $this->greeting->getClientOriginalName();
        }
    }

    /**
     * Put the AWS details on the settings, keeping the saved secret access key if none was entered.
     */
    private function applyAwsSettings(VonageSetting $settings): void
    {
        $settings->aws_access_key_id = $this->awsAccessKeyId ?: null;
        $settings->aws_region = $this->awsRegion ?: null;
        $settings->aws_bucket = $this->awsBucket ?: null;

        if (filled($this->awsSecretAccessKey)) {
            $settings->aws_secret_access_key = trim($this->awsSecretAccessKey);
        }
    }

    /**
     * Create the browser phone's Vonage user and tell the user how it went.
     */
    private function reportProvisioning(VonageUserProvisioner $provisioner, VonageSetting $settings): void
    {
        try {
            $created = $provisioner->ensureDiallerUserExists($settings);
        } catch (ConnectionException) {
            $created = false;
        }

        $this->statusIsWarning = ! $created;
        $this->statusMessage = $created
            ? 'Settings saved. The browser phone is ready to connect.'
            : 'Settings saved, but Vonage rejected the request to create the "'.VonageSetting::DIALLER_USER.'" user. Check that the application ID matches the private key.';
    }
}
