<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dynamic_trade_handler', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('market')->nullable();
            $table->string('symbol')->nullable();
            $table->double('amount')->nullable();
            $table->double('qty')->nullable();
            $table->string('side')->nullable();
            $table->string('status')->default('PENDING');
            $table->integer('tradeAccount')->nullable();
            $table->double('leverage')->nullable();
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
};
