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
        Schema::create('dynamic_trades_future', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('symbol')->nullable();
            $table->string('position')->nullable();
            $table->boolean('allowClose')->nullable();
            $table->double('amount')->nullable();
            $table->double('qty')->nullable();
            $table->integer('leverage')->nullable();
            $table->string('status')->default('PENDING');
            $table->integer('tradeAccount')->nullable();
            $table->float('priceLockOpen')->nullable()->default(0);
            $table->double('priceLockOpenBuffer')->nullable()->default(0.05);
            $table->float('priceLockClose')->nullable()->default(0);
            $table->double('priceLockCloseBuffer')->nullable()->default(0.05);

            $table->tinyInteger('isActive')->nullable();
            $table->timestamps();  // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_trades_future');
    }
};
