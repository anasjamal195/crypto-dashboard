<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_book_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->timestamp('snapshot_time')->index();
            $table->integer('depth')->default(100);
            
            // Storing the full order book data as JSON
            $table->json('raw_data');
            
            // Pre-calculated metrics for faster analysis
            $table->decimal('bid_volume', 30, 8)->nullable();
            $table->decimal('ask_volume', 30, 8)->nullable();
            $table->decimal('volume_imbalance', 10, 4)->nullable();
            $table->decimal('highest_bid', 30, 8)->nullable();
            $table->decimal('lowest_ask', 30, 8)->nullable();
            $table->decimal('spread', 30, 8)->nullable();
            
            // Support and resistance levels
            $table->json('support_levels')->nullable();
            $table->json('resistance_levels')->nullable();
            
            // Areas with thin liquidity
            $table->json('thin_liquidity_areas')->nullable();
            
            // Trading signals based on the order book
            $table->string('signal', 10)->nullable(); // LONG, SHORT, NEUTRAL
            $table->decimal('long_strength', 5, 2)->nullable();
            $table->decimal('short_strength', 5, 2)->nullable();
            $table->json('long_entry_points')->nullable();
            $table->json('short_entry_points')->nullable();
            
            $table->timestamps();
            
            // Add indexes for common queries
            $table->index(['symbol', 'snapshot_time']);
            $table->index(['symbol', 'signal']);
            $table->index(['symbol', 'snapshot_time', 'signal']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_book_snapshots');
    }
};