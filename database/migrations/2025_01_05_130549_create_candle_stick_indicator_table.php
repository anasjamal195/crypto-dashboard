<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ideal_buying_candles', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('interval');
            $table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
            $table->bigInteger('binance_timestamp');
            $table->string('market')->nullable();
            $table->double('open')->nullable();
            $table->double('high')->nullable();
            $table->double('low')->nullable();
            $table->double('close')->nullable();
            $table->double('volume')->nullable();
            $table->double('ma7')->nullable();
            $table->double('ma14')->nullable();
            $table->double('ma25')->nullable();
            $table->double('ma99')->nullable();
            $table->double('rsi6')->nullable();
            $table->double('per')->nullable();
            $table->double('dif')->nullable();
            $table->double('dea')->nullable();
            $table->double('histogram')->nullable();
            $table->double('sar')->nullable();
            $table->tinyInteger('should_buy')->nullable();
            $table->tinyInteger('should_sell')->nullable();
            $table->double('obv')->nullable();
            $table->double('stoch_rsi')->nullable();
            $table->double('stoch_k')->nullable();
            $table->double('stoch_d')->nullable();
            $table->double('previousObvHigh')->nullable();
            $table->double('wr')->nullable();
            $table->double('K')->nullable();
            $table->double('D')->nullable();
            $table->double('J')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('candlestick_data_indicators');
    }
};
