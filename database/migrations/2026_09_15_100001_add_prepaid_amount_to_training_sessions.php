<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `prepaid_amount` is the part of a session's price paid out of a prepayment — the whole price
     * for a session marked `prepaid`, a part for the one the pool ran out on. Two separate calls,
     * because SQLite rebuilds the table to change a column and does not mix that with adding one.
     */
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedInteger('prepaid_amount')->default(0)->after('price'); // grosze
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->enum('payment_status', ['paid', 'balance', 'requested', 'waived', 'prepaid'])
                ->default('balance')
                ->change();
        });
    }

    /**
     * A session paid out of a prepayment stays settled on the way back — it was paid for.
     */
    public function down(): void
    {
        DB::table('training_sessions')->where('payment_status', 'prepaid')->update(['payment_status' => 'paid']);

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->enum('payment_status', ['paid', 'balance', 'requested', 'waived'])
                ->default('balance')
                ->change();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn('prepaid_amount');
        });
    }
};
