<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lives outside database/migrations on purpose: it belongs to the "infra" connection
// and is run by the infra container (see compose.yaml), not by the app's own migrate.
return new class extends Migration
{
    protected $connection = 'infra';

    public function up(): void
    {
        Schema::connection('infra')->create('infra_servers', function (Blueprint $table) {
            $table->string('token', 64)->primary();
            $table->string('name')->nullable();
            $table->string('state', 20);
            $table->dateTime('ready_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('infra')->dropIfExists('infra_servers');
    }
};
