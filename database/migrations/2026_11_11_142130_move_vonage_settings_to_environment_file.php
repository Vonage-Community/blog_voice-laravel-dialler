<?php

use App\Models\VonageSetting;
use App\Services\EnvironmentFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns that were stored encrypted.
     *
     * @var array<int, string>
     */
    private const array ENCRYPTED_COLUMNS = ['private_key', 'aws_access_key_id', 'aws_secret_access_key'];

    /**
     * Copy any saved settings into the .env file, then drop the table that held them.
     */
    public function up(): void
    {
        $row = DB::table('vonage_settings')->first();

        if ($row !== null) {
            $values = [];

            foreach (VonageSetting::ENVIRONMENT_KEYS as $column => $key) {
                $value = $row->{$column} ?? null;

                if ($value === null) {
                    continue;
                }

                $values[$key] = match (true) {
                    $column === 'answerphone_enabled' => (bool) $value,
                    in_array($column, self::ENCRYPTED_COLUMNS, true) => Crypt::decryptString($value),
                    default => (string) $value,
                };
            }

            resolve(EnvironmentFile::class)->write($values);
        }

        Schema::drop('vonage_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('vonage_settings', function (Blueprint $table) {
            $table->id();
            $table->string('application_id')->nullable();
            $table->text('private_key')->nullable();
            $table->string('vonage_number')->nullable();
            $table->string('answerphone_path')->nullable();
            $table->string('answerphone_filename')->nullable();
            $table->boolean('answerphone_enabled')->default(false);
            $table->timestamps();
            $table->text('aws_access_key_id')->nullable();
            $table->text('aws_secret_access_key')->nullable();
            $table->string('aws_region')->nullable();
            $table->string('aws_bucket')->nullable();
            $table->string('public_url')->nullable();
        });
    }
};
