<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cryptocurrency extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cmc_id',
        'name',
        'symbol',
        'slug',
    ];

    /**
     * Portfolio entries that include this cryptocurrency.
     */
    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    /**
     * Historical price snapshots. Built by our backend on each quote poll because
     * CoinMarketCap free API does not provide historical data; recorded_at is the chart X-axis.
     */
    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }
}
