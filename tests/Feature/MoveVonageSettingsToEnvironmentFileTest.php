<?php

use Dotenv\Dotenv;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

test('saved settings are moved from the database into the .env file', function () {
    $migration = require database_path('migrations/2026_11_11_142130_move_vonage_settings_to_environment_file.php');
    $privateKey = privateKeySettings()['private_key'];

    $migration->down();

    DB::table('vonage_settings')->insert([
        'application_id' => '0f4a1c2e-1234-4abc-9def-0123456789ab',
        'private_key' => Crypt::encryptString($privateKey),
        'vonage_number' => '447700900000',
        'answerphone_enabled' => true,
        'aws_secret_access_key' => Crypt::encryptString('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'),
    ]);

    $migration->up();

    $parsed = Dotenv::parse(File::get(app()->environmentFilePath()));

    expect($parsed)
        ->VONAGE_APPLICATION_ID->toBe('0f4a1c2e-1234-4abc-9def-0123456789ab')
        ->VONAGE_PRIVATE_KEY->toBe($privateKey)
        ->VONAGE_NUMBER->toBe('447700900000')
        ->VONAGE_ANSWERPHONE_ENABLED->toBe('true')
        ->RECORDINGS_AWS_SECRET_ACCESS_KEY->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
        ->not->toHaveKey('RECORDINGS_AWS_ACCESS_KEY_ID')
        ->and(Schema::hasTable('vonage_settings'))->toBeFalse();
});
