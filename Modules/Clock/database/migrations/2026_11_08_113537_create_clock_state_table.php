<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clock_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->dateTime('now');
        });

        // one row, ever
        DB::statement('ALTER TABLE clock_state ADD CONSTRAINT clock_state_single_row CHECK (id = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('clock_state');
    }
};
