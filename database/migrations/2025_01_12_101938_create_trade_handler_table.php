<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTradeHandlerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_handler', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('market')->nullable();
            $table->string('symbol')->nullable();
            $table->string('interval')->nullable();
            $table->double('buyPrice')->nullable();
            $table->integer('tradeAccount')->nullable();
            $table->double('targetProfit')->default(0.5);
            $table->double('leverage')->nullable();
            $table->double('stopLoss')->nullable();
            $table->double('stopLossReductionPrecentage')->nullable();
            $table->double('rsiThreshold')->default(20);
            $table->double('obvLimit')->default(20);
            $table->double('stochLimit')->default(80);
            $table->double('wrLimit')->default(-98);
            $table->float('priceLock')->default(0);
            $table->double('priceLockBuffer')->default(0.05);
            $table->tinyInteger('isActive')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trade_handler');
    }
}
