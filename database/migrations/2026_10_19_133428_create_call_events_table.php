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
        Schema::create('call_events', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('call_uuid')->nullable()->index();
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->string('status')->nullable();
            $table->string('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_events');
    }
};
