<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens for the read-only agent API. Only the SHA-256 hash is stored: a stolen database row
     * cannot be replayed as a token, and a lost token is reissued rather than looked up.
     *
     * There is no UI for this — `php artisan agent:token` prints the token once and nothing ever
     * stores the plain text (docs/AGENT-API.md §2).
     */
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Pulpit Maćka" — who is holding it, for revoking the right one
            $table->char('token_hash', 64)->unique(); // hash('sha256', $plain)
            $table->json('scopes')->nullable(); // ["crm.read"]
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable(); // revoking is a column, not a delete: the trail stays
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
