<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('name');
            // we pick this, the infrastructure only ever sees the token: a lost answer can't lose the server
            $table->uuid('infra_token')->unique();
            $table->enum('status', ['pending', 'running', 'terminating', 'terminated', 'failed'])->default('pending');
            $table->dateTime('requested_at');
            // billing window: from the moment it was seen running until termination was requested
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['customer_id', 'status']);
        });

        DB::statement('ALTER TABLE servers ADD CONSTRAINT servers_window_order CHECK (
            started_at IS NULL OR ended_at IS NULL OR ended_at >= started_at
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
