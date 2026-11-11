<?php

namespace App\Services;

use App\Models\VonageSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;

/**
 * Reads the S3 bucket that holds call recordings, using the credentials saved in the settings.
 */
class RecordingBucket
{
    /**
     * The most objects to list at once, so a large bucket cannot stall the page.
     */
    public const int MAX_OBJECTS = 200;

    /**
     * Build a disk for the saved bucket. Failures throw, so they are never mistaken for an empty bucket.
     */
    public function disk(VonageSetting $settings): Filesystem
    {
        return Storage::build([
            'driver' => 's3',
            'key' => $settings->aws_access_key_id,
            'secret' => $settings->aws_secret_access_key,
            'region' => $settings->aws_region,
            'bucket' => $settings->aws_bucket,
            'throw' => true,
        ]);
    }

    /**
     * List the bucket's files using only the listing response, without a request per object.
     *
     * @return array<int, array{path: string, size: ?int, lastModified: ?int}>
     */
    public function objects(VonageSetting $settings): array
    {
        $listing = $this->disk($settings)->listContents('', true);

        return LazyCollection::make(function () use ($listing) {
            foreach ($listing as $item) {
                yield $item;
            }
        })
            ->filter(fn (StorageAttributes $item): bool => $item instanceof FileAttributes)
            ->take(self::MAX_OBJECTS)
            ->map(fn (FileAttributes $file): array => [
                'path' => $file->path(),
                'size' => $file->fileSize(),
                'lastModified' => $file->lastModified(),
            ])
            ->values()
            ->all();
    }
}
