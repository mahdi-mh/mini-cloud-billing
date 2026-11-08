<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one row = one period during which an address sat on a server
        Schema::create('ip_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_address_id')->constrained()->restrictOnDelete();
            $table->foreignId('server_id')->constrained()->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            // = the address while the assignment is open, NULL once closed.
            // A unique index on it means an address can have only one open assignment, whatever the code does.
            $table->unsignedBigInteger('open_ip_id')->nullable()->storedAs('IF(ended_at IS NULL, ip_address_id, NULL)')->unique();

            $table->index(['server_id', 'ended_at']);
            $table->index(['ip_address_id', 'started_at']);
        });

        DB::statement('ALTER TABLE ip_assignments ADD CONSTRAINT ip_assignments_period_order CHECK (
            ended_at IS NULL OR ended_at >= started_at
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_assignments');
    }
};
