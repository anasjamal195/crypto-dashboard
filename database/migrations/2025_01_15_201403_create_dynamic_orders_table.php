<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('dynamic_orders', function (Blueprint $table) {
            $table->id();

            $table->string('symbol', 20)->nullable();
            $table->string('interval', 20)->nullable();
            $table->string('market', 20)->nullable();
            $table->float('amount', 20)->nullable();
            $table->string('orderId', 500)->nullable();
            $table->string('status', 20)->nullable();
            $table->string('type', 10)->nullable();
            $table->string('side', 5)->nullable();
            $table->float('price')->nullable();
            $table->float('qty')->nullable();
            $table->integer('trade_acc')->nullable();
            $table->integer('coin_reports_live_id')->nullable();
            $table->double('targetProfit')->nullable();
            $table->enum('trade_status', ['open', 'close'])->nullable();


            $table->double('leverage')->nullable();
            $table->double('liqPrice')->default(0);
            $table->float('commission')->nullable();
            $table->float('commissionUSDT')->nullable();
            $table->string('commission_asset', 11)->nullable();
            $table->float('currentPrice')->nullable();
            $table->double('previousPrice')->nullable();
            $table->float('stopLoss')->default(0);
            $table->float('stopLossReductionPrecentage')->default(0.25);
            $table->longText('pair_id')->nullable();
            $table->double('obvPreviousHigh')->nullable();
            $table->double('rsiAtBuy')->nullable();
            $table->double('obvAtBuy')->nullable();
            $table->double('rsiMin')->nullable();
            $table->double('obvMin')->nullable();
            $table->double('priceMin')->nullable();
            $table->double('stopLossPrice')->nullable();
            $table->double('stopLossOrderId')->nullable();
            $table->timestamps();  // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
