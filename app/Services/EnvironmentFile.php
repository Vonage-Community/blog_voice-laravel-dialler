<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Writes values into the application's .env file.
 */
class EnvironmentFile
{
    /**
     * Set each key in the .env file, replacing its line or adding one to the end.
     *
     * @param  array<string, string|bool|null>  $values
     */
    public function write(array $values): void
    {
        Cache::lock('environment-file', 10)->block(5, function () use ($values): void {
            $path = app()->environmentFilePath();
            $contents = File::exists($path) ? File::get($path) : '';

            foreach ($values as $key => $value) {
                $line = $key.'='.$this->format($value);
                $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

                $contents = preg_match($pattern, $contents) === 1
                    ? (string) preg_replace_callback($pattern, fn (): string => $line, $contents, 1)
                    : ltrim(rtrim($contents, "\n")."\n".$line."\n");
            }

            File::replace($path, $contents);
        });

        if (app()->configurationIsCached()) {
            Artisan::call('config:cache');
        }
    }

    /**
     * Quote and escape a value so it fits on one line and reads back exactly as given.
     */
    private function format(string|bool|null $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            default => '"'.str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '\\r', '\\n'], $value).'"',
        };
    }
}
