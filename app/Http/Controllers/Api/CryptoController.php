<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cryptocurrency;
use App\Models\Portfolio;
use App\Models\PriceSnapshot;
use App\Services\CoinMarketCapService;
use Illuminate\Http\JsonResponse;

/**
 * Serves portfolio crypto data and triggers historical persistence (snapshots).
 *
 * The controller does not call CoinMarketCap directly; it uses CoinMarketCapService
 * so the API key and rate limiting stay in one place. After receiving fresh quotes,
 * it persists one row per coin in price_snapshots (Snapshot Pattern) to build
 * our own timeline, since the free CMC API does not provide historical data.
 */
class CryptoController extends Controller
{
    /**
     * Return portfolio cryptocurrencies with their latest quotes, and persist
     * a price snapshot for each to build local history for charts.
     *
     * Flow: load portfolio with cryptos → get CMC IDs → fetch quotes via service
     * (cached 60s) → for each quote, save snapshot → return combined JSON.
     */
    public function index(CoinMarketCapService $cmcService): JsonResponse
    {
        $portfolios = Portfolio::with('cryptocurrency')->get();

        $cmcIds = $portfolios->pluck('cryptocurrency.cmc_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($cmcIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Portfolio is empty. Add cryptocurrencies to see quotes.',
            ]);
        }

        try {
            $response = $cmcService->getQuotes($cmcIds);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Unable to fetch quotes: ' . $e->getMessage(),
            ], 502);
        }

        $cryptosByCmcId = Cryptocurrency::whereIn('cmc_id', $cmcIds)->get()->keyBy('cmc_id');
        $recordedAt = now();

        foreach ($response['data'] ?? [] as $cmcId => $apiCoin) {
            $crypto = $cryptosByCmcId->get((int) $cmcId);
            if (! $crypto) {
                continue;
            }

            $quote = $apiCoin['quote']['USD'] ?? null;
            if (! $quote) {
                continue;
            }

            PriceSnapshot::create([
                'cryptocurrency_id' => $crypto->id,
                'price_usd' => $quote['price'] ?? 0,
                'percent_change_24h' => $quote['percent_change_24h'] ?? 0,
                'volume_24h' => $quote['volume_24h'] ?? 0,
                'market_cap' => $quote['market_cap'] ?? 0,
                'recorded_at' => $recordedAt,
            ]);
        }

        $data = $this->buildPortfolioQuotesResponse($portfolios, $response);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Build the response array: portfolio order, each item with coin info + current quote.
     *
     * @param  \Illuminate\Support\Collection<int, Portfolio>  $portfolios
     * @param  array{data: array<int, array>}  $response
     * @return array<int, array<string, mixed>>
     */
    private function buildPortfolioQuotesResponse($portfolios, array $response): array
    {
        $data = [];
        $apiData = $response['data'] ?? [];

        foreach ($portfolios as $portfolio) {
            $crypto = $portfolio->cryptocurrency;
            if (! $crypto) {
                continue;
            }

            $apiCoin = $apiData[(string) $crypto->cmc_id] ?? null;
            $quote = $apiCoin['quote']['USD'] ?? null;

            $data[] = [
                'portfolio_id' => $portfolio->id,
                'cryptocurrency_id' => $crypto->id,
                'cmc_id' => $crypto->cmc_id,
                'name' => $crypto->name,
                'symbol' => $crypto->symbol,
                'slug' => $crypto->slug,
                'price_usd' => $quote['price'] ?? null,
                'percent_change_24h' => $quote['percent_change_24h'] ?? null,
                'volume_24h' => $quote['volume_24h'] ?? null,
                'market_cap' => $quote['market_cap'] ?? null,
            ];
        }

        return $data;
    }
}
