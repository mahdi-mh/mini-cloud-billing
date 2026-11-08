<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the fixed pool: rows are created by the seeder, never by customers
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('address', 45)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
    }
};
