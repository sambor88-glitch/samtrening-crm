<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The daily reminder run sends at most one message per client per week, and this is how it
     * remembers. Reading it out of the activity log would tie the rule to a text.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('last_reminder_at')->nullable()->after('archived');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('last_reminder_at');
        });
    }
};
