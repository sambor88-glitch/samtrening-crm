<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the money actually arrived, as opposed to when the session was held. The two differ
     * whenever a client settles an old debt or pays for several sessions at once, and the agent
     * API reports a month's takings as a cash flow — what came in during the month, whatever it
     * was for (docs/AGENT-API.md §5).
     *
     * Nothing recorded this before, so rows settled up to now keep a null here and the report
     * falls back to the session date for them. New payments carry the real moment.
     */
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('payment_status');

            // The monthly cash-flow figure scans this column across the whole studio.
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
