<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money a client paid before the sessions happened. It is not a balance column: which sessions
     * it pays for is worked out again from sessions every time (Billing\PrepaymentPool).
     */
    public function up(): void
    {
        Schema::create('prepayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->unsignedInteger('amount'); // grosze
            $table->date('paid_on');
            $table->softDeletes(); // a mistaken entry comes off the card with "Cofnij", like a session
            $table->timestamps();

            $table->index(['client_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepayments');
    }
};
