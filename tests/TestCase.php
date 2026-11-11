<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /**
     * Point .env writes at a throwaway file, so saving settings in a test never touches the real one.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $environmentFile = (string) tempnam(sys_get_temp_dir(), 'dialler-env-');

        $this->app->useEnvironmentPath(dirname($environmentFile))->loadEnvironmentFrom(basename($environmentFile));

        $this->beforeApplicationDestroyed(fn () => File::delete($environmentFile));
    }
}
