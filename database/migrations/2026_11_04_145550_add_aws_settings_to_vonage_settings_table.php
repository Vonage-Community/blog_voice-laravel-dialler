<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vonage_settings', function (Blueprint $table) {
            $table->text('aws_access_key_id')->nullable();
            $table->text('aws_secret_access_key')->nullable();
            $table->string('aws_region')->nullable();
            $table->string('aws_bucket')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vonage_settings', function (Blueprint $table) {
            $table->dropColumn(['aws_access_key_id', 'aws_secret_access_key', 'aws_region', 'aws_bucket']);
        });
    }
};
