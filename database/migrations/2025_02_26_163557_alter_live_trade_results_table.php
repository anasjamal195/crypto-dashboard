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
            $table->float('realizedPnl', 20)->nullable();
            $table->float('feeUsdt', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_trades_future_results', function (Blueprint $table) {
            $table->dropColumn('realizedPnl', 20);
            $table->dropColumn('feeUsdt', 20);
        });
    }
};
