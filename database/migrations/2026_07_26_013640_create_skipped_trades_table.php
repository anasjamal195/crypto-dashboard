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
        Schema::create('skipped_trades', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('color')->nullable();
            $table->string('formula')->nullable();
            $table->string('position')->nullable();
            $table->longText('buyingCandle')->nullable();
            $table->longText('sellingCandle')->nullable();
            $table->longText('skipping_reasons')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skipped_trades');
    }
};
