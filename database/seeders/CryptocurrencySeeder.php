<?php

namespace Database\Seeders;

use App\Models\Cryptocurrency;
use Illuminate\Database\Seeder;

class CryptocurrencySeeder extends Seeder
{
    /**
     * Seed the top 5 cryptocurrencies using real CoinMarketCap IDs.
     * These IDs are required for exact API lookups (e.g. /v1/cryptocurrency/quotes/latest?id=1).
     */
    public function run(): void
    {
        $coins = [
            ['cmc_id' => 1,    'name' => 'Bitcoin',  'symbol' => 'BTC',  'slug' => 'bitcoin'],
            ['cmc_id' => 1027, 'name' => 'Ethereum', 'symbol' => 'ETH',  'slug' => 'ethereum'],
            ['cmc_id' => 1839, 'name' => 'BNB',      'symbol' => 'BNB',  'slug' => 'bnb'],
            ['cmc_id' => 5426, 'name' => 'Solana',   'symbol' => 'SOL',  'slug' => 'solana'],
            ['cmc_id' => 2010, 'name' => 'Cardano',  'symbol' => 'ADA',  'slug' => 'cardano'],
        ];

        foreach ($coins as $coin) {
            Cryptocurrency::updateOrCreate(
                ['cmc_id' => $coin['cmc_id']],
                $coin
            );
        }
    }
}
