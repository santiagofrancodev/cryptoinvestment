<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSnapshot extends Model
{
    /**
     * Snapshot pattern: we persist one row per poll (e.g. every 60s) from the
     * CoinMarketCap API response. This builds our own history since the free
     * API does not expose historical data. recorded_at is used as the X-axis in Chart.js.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cryptocurrency_id',
        'price_usd',
        'percent_change_24h',
        'volume_24h',
        'market_cap',
        'recorded_at',
    ];

    /**
     * Cast recorded_at for range queries and chart formatting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'price_usd' => 'decimal:8',
            'percent_change_24h' => 'decimal:4',
            'volume_24h' => 'decimal:2',
            'market_cap' => 'decimal:2',
        ];
    }

    /**
     * The cryptocurrency this snapshot belongs to.
     */
    public function cryptocurrency(): BelongsTo
    {
        return $this->belongsTo(Cryptocurrency::class);
    }
}
