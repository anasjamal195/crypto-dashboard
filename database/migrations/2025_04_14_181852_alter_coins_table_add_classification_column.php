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

        Schema::table('coins', function (Blueprint $table) {
            $table->string('classification')->nullable()->after('symbol');
            $table->boolean('is_meme_coin')->nullable()->after('symbol');
            $table->boolean('is_altcoin')->nullable()->after('symbol');
            $table->boolean('is_nft')->nullable()->after('symbol');
            $table->boolean('is_defi')->nullable()->after('symbol');
            $table->boolean('is_metaverse')->nullable()->after('symbol');
            $table->boolean('is_web3')->nullable()->after('symbol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('coins', function (Blueprint $table) {
            $table->dropColumn('classification');
            $table->dropColumn('is_meme_coin');
            $table->dropColumn('is_altcoin');
            $table->dropColumn('is_nft');
            $table->dropColumn('is_defi');
            $table->dropColumn('is_metaverse');
            $table->dropColumn('is_web3');
        });
    }
};
