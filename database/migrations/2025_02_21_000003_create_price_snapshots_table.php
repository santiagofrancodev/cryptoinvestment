<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Snapshot pattern: CoinMarketCap free API does not provide historical data.
     * We persist a row on each polling cycle (e.g. every 60s) to build our own
     * time-series locally. Chart.js X-axis uses recorded_at for timeline verification.
     */
    public function up(): void
    {
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cryptocurrency_id')->constrained('cryptocurrencies')->cascadeOnDelete();
            $table->decimal('price_usd', 18, 8);
            $table->decimal('percent_change_24h', 8, 4);
            $table->decimal('volume_24h', 20, 2);
            $table->decimal('market_cap', 20, 2);
            $table->timestamp('recorded_at')->comment('Snapshot time; used as X-axis in charts, indexed for range queries');
            $table->timestamps();

            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
    }
};
