<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // price per started hour, valid from `effective_from` until the next row of the same type
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['server', 'ip']);
            $table->unsignedBigInteger('amount');
            $table->dateTime('effective_from');

            $table->unique(['type', 'effective_from']);
        });

        DB::statement('ALTER TABLE prices ADD CONSTRAINT prices_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
