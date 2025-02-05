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
        Schema::table('live_trades_future_results', function (Blueprint $table) {
            $table->string('pairId')->nullable();
            $table->float('currentPrice', 20)->nullable();
            $table->float('currentSupport', 20)->nullable();
            $table->float('currentResistance', 20)->nullable();
            $table->float('currentProfit', 20)->nullable();
            $table->float('targetProfit', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_trades_future_results', function (Blueprint $table) {
            $table->dropColumn('pairId');
            $table->dropColumn('currentPrice');
            $table->dropColumn('currentSupport');
            $table->dropColumn('currentResistance');
            $table->dropColumn('currentProfit');
            $table->dropColumn('targetProfit');
        });
    }
};
