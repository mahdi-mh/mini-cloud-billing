<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one row = one resource, one hour. The unique key below is what makes billing exactly-once.
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('billable_type', ['server', 'ip']);
            // the server id, or the ip address id: so an ip is billed once per hour, to one payer
            $table->unsignedBigInteger('billable_id');
            $table->dateTime('hour_start');
            $table->unsignedBigInteger('amount');
            // which server it was for (the one holding the ip, for ip charges)
            $table->foreignId('server_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('created_at');

            $table->unique(['billable_type', 'billable_id', 'hour_start']);
            $table->index(['customer_id', 'hour_start']);
            $table->index('server_id');
        });

        DB::statement('ALTER TABLE charges ADD CONSTRAINT charges_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
