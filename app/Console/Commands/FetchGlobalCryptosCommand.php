<?php

namespace App\Console\Commands;

use App\Models\Cryptocurrency;
use App\Services\CoinMarketCapService;
use Illuminate\Console\Command;

class FetchGlobalCryptosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:fetch-global
                            {--limit=100 : Number of top cryptocurrencies to fetch (1-5000)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch top cryptocurrencies from CoinMarketCap listings/latest and store them in the cryptocurrencies table for search.';

    /**
     * Execute the console command.
     *
     * Fetches from /v1/cryptocurrency/listings/latest and upserts by cmc_id
     * so that frontend search (e.g. "pax", "ltc") returns results.
     */
    public function handle(CoinMarketCapService $cmcService): int
    {
        $limit = (int) $this->option('limit');
        $limit = max(1, min(5000, $limit));

        $this->info("Fetching top {$limit} cryptocurrencies from CoinMarketCap…");

        try {
            $response = $cmcService->getListingsLatest($limit);
        } catch (\Throwable $e) {
            $this->error('Failed to fetch listings: ' . $e->getMessage());
            return self::FAILURE;
        }

        $items = $response['data'] ?? [];
        if (empty($items)) {
            $this->warn('No data returned from API.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $cmcId = (int) ($item['id'] ?? 0);
            $name = (string) ($item['name'] ?? '');
            $symbol = (string) ($item['symbol'] ?? '');
            $slug = (string) ($item['slug'] ?? '');

            if ($cmcId < 1 || $name === '' || $symbol === '') {
                $bar->advance();
                continue;
            }

            $existing = Cryptocurrency::where('cmc_id', $cmcId)->first();
            Cryptocurrency::updateOrCreate(
                ['cmc_id' => $cmcId],
                ['name' => $name, 'symbol' => $symbol, 'slug' => $slug ?: \Illuminate\Support\Str::slug($name)]
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Created: {$created}, Updated: {$updated}.");

        return self::SUCCESS;
    }
}
