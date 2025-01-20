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
        Schema::table('dynamic_trade_handler', function (Blueprint $table) {
            $table->string('position')->nullable()->after('side');
            $table->string('openOrderId')->nullable()->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_trade_handler', function (Blueprint $table) {
            $table->dropColumn('position');
            $table->dropColumn('openOrderId');
        });
    }
};
