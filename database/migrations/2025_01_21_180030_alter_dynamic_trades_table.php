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
        Schema::table('dynamic_trades_future', function (Blueprint $table) {
            $table->float('stopLoss')->nullable()->default(0);
            $table->double('stopLossBuffer')->nullable()->default(0.00);
        });
        Schema::table('dynamic_trades_spot', function (Blueprint $table) {
            $table->float('stopLoss')->nullable()->default(0);
            $table->double('stopLossBuffer')->nullable()->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_trades_future', function (Blueprint $table) {
            $table->dropColumn('stopLoss');
            $table->dropColumn('stopLossBuffer');
        });
        Schema::table('dynamic_trades_spot', function (Blueprint $table) {
            $table->dropColumn('stopLoss');
            $table->dropColumn('stopLossBuffer');
        });
    }
};
