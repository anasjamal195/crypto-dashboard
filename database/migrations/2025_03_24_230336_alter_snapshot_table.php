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
        Schema::table('order_book_snapshots', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->after('depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_book_snapshots', function (Blueprint $table) {
            $table->dropColumn('type', 20);
        });
    }
};
