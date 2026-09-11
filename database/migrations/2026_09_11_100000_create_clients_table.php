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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users');
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('rate'); // grosze, in steps of 500
            $table->text('goal')->nullable();
            $table->text('baseline')->nullable();
            $table->text('contraindications')->nullable(); // health data, encrypted by the model cast
            $table->text('trainer_notes')->nullable();
            $table->text('next_session_plan')->nullable();
            $table->string('guardian')->nullable(); // required for clients under 18
            $table->boolean('consent_given')->default(false);
            $table->date('consent_date')->nullable();
            $table->string('company_name')->nullable();
            $table->string('tax_id', 15)->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();

            $table->index(['trainer_id', 'archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
