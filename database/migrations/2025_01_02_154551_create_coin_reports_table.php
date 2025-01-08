<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCoinReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coin_reports', function (Blueprint $table) {
            $table->id(); // Automatically creates an UNSIGNED BIGINT equivalent column.
            $table->string('symbol', 10)->nullable();
            $table->string('interval', 10)->nullable();
            $table->string('market', 10)->nullable();
            $table->json('buyingCandle')->nullable();
            $table->json('sellingCandle')->nullable();
            $table->json('buyingAverages')->nullable();
            $table->decimal('buyingPrice', 10, 4)->nullable();
            $table->decimal('liquidationPrice', 10, 4)->nullable();
            $table->decimal('sellingPrice', 10, 4)->nullable();
            $table->double('lowestPrice')->nullable();
            $table->double('lowestPricePercentage')->nullable();
            $table->decimal('profit', 10, 2)->nullable();
            $table->integer('duration')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coin_reports');
    }
}
