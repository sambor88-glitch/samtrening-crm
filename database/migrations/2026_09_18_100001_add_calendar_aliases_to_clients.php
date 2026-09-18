<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How this client is written in Google Calendar, so the dashboard can match an event to a
     * card. Null means "work it out from the name" — Clients\CalendarAliases does that, and
     * covers the ordinary cases on its own. The column is for the ones a name cannot give:
     * "Ula I Gosia" for a pair training together, or a first name the calendar never spells out.
     *
     * Nullable on purpose. An empty array is a decision ("this client has no aliases"); null is
     * the absence of one, and the generated list stands in for it.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('calendar_aliases')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('calendar_aliases');
        });
    }
};
