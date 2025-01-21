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
        Schema::create('dynamic_trades_future_results', function (Blueprint $table) {
            $table->id();
            $table->string('orderId', 500)->nullable();
            $table->string('tradeId', 500)->nullable();
            $table->string('symbol', 20)->nullable();
            $table->string('side', 5)->nullable();
            $table->string('type', 5)->nullable();
            $table->float('amount', 20)->nullable();
            $table->float('qty')->nullable();
            $table->integer('leverage')->nullable();
            $table->double('liqPrice')->nullable();
            $table->float('price')->nullable();
            $table->integer('trade_acc')->nullable();
            $table->timestamps();  // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_trades_future_results');
    }
};
