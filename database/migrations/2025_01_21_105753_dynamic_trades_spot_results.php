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
        Schema::create('dynamic_trades_spot_results', function (Blueprint $table) {
            $table->id();
            $table->string('orderId', 500)->nullable();
            $table->string('tradeId', 500)->nullable();
            $table->string('symbol', 20)->nullable();
            $table->string('side', 5)->nullable();
            $table->string('status')->default('PENDING');
            $table->float('amount', 20)->nullable();
            $table->float('qty')->nullable();
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
        Schema::drop('dynamic_trades_spot_results');
    }
};
