<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Not `sessions` — that table belongs to Laravel's session driver.
     */
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->date('date');
            $table->string('service');
            $table->unsignedInteger('price'); // grosze; may differ from the client's rate
            $table->enum('kind', ['completed', 'cancelled', 'no_show'])->default('completed');
            $table->enum('payment_status', ['paid', 'balance', 'requested', 'waived'])->default('balance');
            $table->text('notes')->nullable(); // health data, encrypted by the model cast
            $table->softDeletes(); // deleting a session never removes the row
            $table->timestamps();

            $table->index(['client_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
