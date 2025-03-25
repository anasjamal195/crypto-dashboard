<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('worker_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id')->nullable();
            $table->string('symbol')->nullable();
            $table->string('trigger_id')->nullable();
            $table->string('trade_handler_id')->nullable();
            $table->boolean('trade_status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_symbols');
    }
};
