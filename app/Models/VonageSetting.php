<?php

namespace App\Models;

use App\Services\EnvironmentFile;
use Illuminate\Support\Facades\Storage;
use JsonSerializable;

/**
 * The dialler's Vonage configuration, kept in the .env file so its secrets never reach the database.
 */
class VonageSetting implements JsonSerializable
{
    /**
     * The name of the Vonage user the browser phone signs in as.
     */
    public const string DIALLER_USER = 'dialler';

    /**
     * The .env key each setting is written to. Each is read back through config('services.vonage.*').
     *
     * @var array<string, string>
     */
    public const array ENVIRONMENT_KEYS = [
        'application_id' => 'VONAGE_APPLICATION_ID',
        'private_key' => 'VONAGE_PRIVATE_KEY',
        'vonage_number' => 'VONAGE_NUMBER',
        'answerphone_path' => 'VONAGE_ANSWERPHONE_PATH',
        'answerphone_filename' => 'VONAGE_ANSWERPHONE_FILENAME',
        'answerphone_enabled' => 'VONAGE_ANSWERPHONE_ENABLED',
        'aws_access_key_id' => 'RECORDINGS_AWS_ACCESS_KEY_ID',
        'aws_secret_access_key' => 'RECORDINGS_AWS_SECRET_ACCESS_KEY',
        'aws_region' => 'RECORDINGS_AWS_REGION',
        'aws_bucket' => 'RECORDINGS_AWS_BUCKET',
        'public_url' => 'VONAGE_PUBLIC_URL',
    ];

    /**
     * The secrets left out when the settings are serialized.
     *
     * @var array<int, string>
     */
    private const array HIDDEN = ['private_key', 'aws_access_key_id', 'aws_secret_access_key'];

    public ?string $application_id = null;

    public ?string $private_key = null;

    public ?string $vonage_number = null;

    public ?string $answerphone_path = null;

    public ?string $answerphone_filename = null;

    public bool $answerphone_enabled = false;

    public ?string $aws_access_key_id = null;

    public ?string $aws_secret_access_key = null;

    public ?string $aws_region = null;

    public ?string $aws_bucket = null;

    public ?string $public_url = null;

    /**
     * The values as last read or saved, so saving only writes what changed.
     *
     * @var array<string, string|bool|null>
     */
    private array $original = [];

    /**
     * Read the settings from the configuration loaded from the .env file.
     */
    public static function current(): self
    {
        $settings = new self;

        foreach (array_keys(self::ENVIRONMENT_KEYS) as $attribute) {
            $value = config("services.vonage.{$attribute}");

            $settings->{$attribute} = $attribute === 'answerphone_enabled'
                ? (bool) $value
                : (blank($value) ? null : (string) $value);
        }

        $settings->original = $settings->attributes();

        return $settings;
    }

    /**
     * Change the given settings and save them.
     *
     * @param  array<string, string|bool|null>  $attributes
     */
    public function update(array $attributes): void
    {
        foreach ($attributes as $attribute => $value) {
            $this->{$attribute} = $value;
        }

        $this->save();
    }

    /**
     * Write the changed settings to the .env file and apply them to the running app.
     */
    public function save(): void
    {
        $changed = array_filter(
            $this->attributes(),
            fn (string|bool|null $value, string $attribute): bool => $value !== $this->original[$attribute],
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changed === []) {
            return;
        }

        resolve(EnvironmentFile::class)->write(
            collect($changed)->mapWithKeys(fn (string|bool|null $value, string $attribute): array => [self::ENVIRONMENT_KEYS[$attribute] => $value])->all()
        );

        config(collect($changed)->mapWithKeys(fn (string|bool|null $value, string $attribute): array => ["services.vonage.{$attribute}" => $value])->all());

        $this->original = $this->attributes();
    }

    /**
     * Whether enough is configured for the browser to connect to Vonage.
     */
    public function isConfigured(): bool
    {
        return filled($this->application_id) && filled($this->private_key);
    }

    /**
     * Whether everything the dialler needs before it can be used has been added.
     */
    public function isReadyToDial(): bool
    {
        return $this->isConfigured() && filled($this->vonage_number) && $this->hasAnswerphoneGreeting() && $this->hasRecordingBucket();
    }

    /**
     * Whether the AWS keys, region and bucket that hold call recordings have been added.
     */
    public function hasRecordingBucket(): bool
    {
        return filled($this->aws_access_key_id) && filled($this->aws_secret_access_key) && filled($this->aws_region) && filled($this->aws_bucket);
    }

    /**
     * Get the address Vonage should use for a route, preferring the public tunnel when one is running.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function publicRoute(string $routeName, array $parameters = []): string
    {
        return blank($this->public_url)
            ? route($routeName, $parameters)
            : rtrim((string) $this->public_url, '/').route($routeName, $parameters, false);
    }

    /**
     * Whether an answerphone greeting has been uploaded.
     */
    public function hasAnswerphoneGreeting(): bool
    {
        return filled($this->answerphone_path);
    }

    /**
     * When the greeting was last uploaded, so its address changes and players fetch the new file.
     */
    public function greetingVersion(): ?int
    {
        return $this->hasAnswerphoneGreeting() && Storage::disk('local')->exists((string) $this->answerphone_path)
            ? Storage::disk('local')->lastModified((string) $this->answerphone_path)
            : null;
    }

    /**
     * Serialize the settings without their secrets.
     *
     * @return array<string, string|bool|null>
     */
    public function jsonSerialize(): array
    {
        return collect($this->attributes())->except(self::HIDDEN)->all();
    }

    /**
     * @return array<string, string|bool|null>
     */
    private function attributes(): array
    {
        return collect(self::ENVIRONMENT_KEYS)->map(fn (string $key, string $attribute): string|bool|null => $this->{$attribute})->all();
    }
}
