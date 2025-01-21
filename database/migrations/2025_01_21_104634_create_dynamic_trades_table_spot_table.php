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
        Schema::create('dynamic_trades_spot', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('symbol')->nullable();
            $table->double('amount')->nullable();
            $table->double('qty')->nullable();
            $table->string('side')->nullable();
            $table->string('status')->default('PENDING');
            $table->integer('tradeAccount')->nullable();
            $table->float('priceLockBuy')->nullable()->default(0);
            $table->double('priceLockBuyBuffer')->nullable()->default(0.05);
            $table->float('priceLockSell')->nullable()->default(0);
            $table->double('priceLockSellBuffer')->nullable()->default(0.05);

            $table->tinyInteger('isActive')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_trades_spot');
    }
};
