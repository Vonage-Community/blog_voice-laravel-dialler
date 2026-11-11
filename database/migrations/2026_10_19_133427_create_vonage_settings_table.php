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
        Schema::create('vonage_settings', function (Blueprint $table) {
            $table->id();
            $table->string('application_id')->nullable();
            $table->text('private_key')->nullable();
            $table->string('vonage_number')->nullable();
            $table->string('answerphone_path')->nullable();
            $table->string('answerphone_filename')->nullable();
            $table->boolean('answerphone_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vonage_settings');
    }
};
