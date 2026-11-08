<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['topup', 'charge', 'refund']);
            // minor units, signed: money in is positive, money out is negative
            $table->bigInteger('amount');
            // same key twice = same movement, so a retry can never move money twice
            $table->string('idempotency_key', 191)->unique();
            $table->string('description')->nullable();
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->dateTime('created_at');

            $table->index(['customer_id', 'created_at']);
        });

        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_sign CHECK (
            (type = 'topup' AND amount > 0) OR (type = 'refund' AND amount > 0) OR (type = 'charge' AND amount < 0)
        )");

        // the ledger is history: no edits, no deletes, not even by mistake in a console
        DB::unprepared("CREATE TRIGGER ledger_entries_no_update BEFORE UPDATE ON ledger_entries
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries is append-only'");
        DB::unprepared("CREATE TRIGGER ledger_entries_no_delete BEFORE DELETE ON ledger_entries
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries is append-only'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
