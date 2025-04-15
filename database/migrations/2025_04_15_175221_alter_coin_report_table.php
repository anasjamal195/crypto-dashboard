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
        Schema::table('coin_reports', function (Blueprint $table) {
            $table->dropColumn('buyingAverages');
            $table->dropColumn('snapshot_id');

            $table->json('openingVolumes')->nullable();
            $table->json('closingVolumes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coin_reports', function (Blueprint $table) {
            $table->json('buyingAverages')->nullable();
            $table->string('snapshot_id')->nullable()->after('market');


            $table->dropColumn('openingVolumes');
            $table->dropColumn('closingVolumes');
        });
    }
};
