<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cryptocurrency;
use App\Models\Portfolio;
use App\Models\PriceSnapshot;
use App\Services\CoinMarketCapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $portfolios = Portfolio::with('cryptocurrency')->orderBy('created_at', 'desc')->get();

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
     * Search cryptocurrencies by name or symbol.
     *
     * This method only touches local database state; it does not call CoinMarketCap.
     * It is used by the frontend search box to let users add assets to their portfolio.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Empty query.',
            ]);
        }

        $results = Cryptocurrency::query()
            ->where(function ($query) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $query->where('name', 'LIKE', $like)
                    ->orWhere('symbol', 'LIKE', $like);
            })
            ->orderBy('name')
            ->limit(20)
            ->get([
                'id',
                'cmc_id',
                'name',
                'symbol',
                'slug',
            ]);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Add a cryptocurrency to the portfolio (idempotent).
     *
     * The controller validates the input and ensures we do not create duplicate
     * portfolio rows for the same cryptocurrency.
     */
    public function storePortfolio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cryptocurrency_id' => ['required', 'integer', 'exists:cryptocurrencies,id'],
        ]);

        $portfolio = Portfolio::firstOrCreate([
            'cryptocurrency_id' => $validated['cryptocurrency_id'],
        ]);

        $portfolio->load('cryptocurrency');

        return response()->json([
            'success' => true,
            'data' => $portfolio,
            'message' => 'Cryptocurrency added to portfolio.',
        ], 201);
    }

    /**
     * Remove a cryptocurrency from the portfolio by portfolio entry id.
     */
    public function destroyPortfolio(int $id): JsonResponse
    {
        $portfolio = Portfolio::find($id);
        if (! $portfolio) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Portfolio entry not found.',
            ], 404);
        }
        $portfolio->delete();
        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Removed from portfolio.',
        ]);
    }

    /**
     * Return historical price data for a cryptocurrency.
     *
     * Tries CoinMarketCap v2 API first. On failure (e.g. free tier, 402/403), falls back
     * to local price_snapshots so the chart still works.
     */
    public function history(int $cmcId, Request $request, CoinMarketCapService $cmcService): JsonResponse
    {
        $crypto = Cryptocurrency::where('cmc_id', $cmcId)->first();
        if (! $crypto) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Cryptocurrency not found for given CoinMarketCap ID.',
            ], 404);
        }

        $validated = $request->validate([
            'range' => ['nullable', 'string', 'in:7d,30d,1y,24h'],
        ]);
        $range = $validated['range'] ?? '7d';

        try {
            $result = $cmcService->getHistory($crypto->cmc_id, $range);
        } catch (\Throwable $e) {
            $result = $this->getHistoryFromSnapshots($crypto, $range);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'cryptocurrency' => [
                    'id' => $crypto->id,
                    'cmc_id' => $crypto->cmc_id,
                    'name' => $crypto->name,
                    'symbol' => $crypto->symbol,
                    'slug' => $crypto->slug,
                ],
                'snapshots' => $result['snapshots'],
                'labels' => $result['labels'],
                'chart_data' => $result['data'],
            ],
        ]);
    }

    /**
     * Return historical data for multiple cryptocurrencies at once (bulk).
     * Used for the "Comparar Todo el Portafolio" chart: one series per coin.
     *
     * GET /api/crypto/history-bulk?ids=1,1027,5426&range=7d
     * Response: { "1": { "symbol": "BTC", "name": "Bitcoin", "snapshots": [...] }, ... }
     */
    public function historyBulk(Request $request, CoinMarketCapService $cmcService): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'string', 'regex:/^[\d,]+$/'],
            'range' => ['nullable', 'string', 'in:7d,30d,1y,24h'],
        ]);
        $range = $validated['range'] ?? '7d';
        $cmcIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $validated['ids'])))));

        if (empty($cmcIds)) {
            return response()->json([
                'success' => true,
                'data' => (object) [],
                'message' => 'No valid IDs provided.',
            ]);
        }

        $result = [];
        foreach ($cmcIds as $cmcId) {
            $crypto = Cryptocurrency::where('cmc_id', $cmcId)->first();
            if (! $crypto) {
                continue;
            }
            try {
                $history = $cmcService->getHistory($crypto->cmc_id, $range);
            } catch (\Throwable $e) {
                $history = $this->getHistoryFromSnapshots($crypto, $range);
            }
            $result[(string) $cmcId] = [
                'symbol' => $crypto->symbol,
                'name' => $crypto->name,
                'snapshots' => $history['snapshots'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Fallback: load history from local price_snapshots when CMC v2 API is unavailable.
     *
     * @return array{snapshots: array, labels: array, data: array}
     */
    private function getHistoryFromSnapshots(Cryptocurrency $crypto, string $range): array
    {
        $to = now();
        $from = match (strtolower($range)) {
            '1y' => $to->copy()->subYear(),
            '30d' => $to->copy()->subDays(30),
            '24h' => $to->copy()->subDay(),
            default => $to->copy()->subDays(7),
        };

        $snapshots = PriceSnapshot::query()
            ->where('cryptocurrency_id', $crypto->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'price_usd']);

        $list = [];
        $labels = [];
        foreach ($snapshots as $s) {
            $at = $s->recorded_at instanceof \DateTimeInterface
                ? $s->recorded_at->format('c')
                : (string) $s->recorded_at;
            $list[] = [
                'recorded_at' => $at,
                'price_usd' => (float) $s->price_usd,
            ];
            $labels[] = $at;
        }

        return [
            'snapshots' => $list,
            'labels' => $labels,
            'data' => array_column($list, 'price_usd'),
        ];
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
