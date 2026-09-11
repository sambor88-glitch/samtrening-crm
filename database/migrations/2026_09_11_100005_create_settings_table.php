<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A single row with the studio-wide rules.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('sms_provider')->nullable();
            $table->boolean('reminders_enabled')->default(true);
            $table->unsignedSmallInteger('reminder_threshold_days')->default(14);
            $table->unsignedSmallInteger('free_cancellation_hours')->default(24);
            $table->unsignedSmallInteger('retention_months')->default(60);
            $table->boolean('ticker_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
