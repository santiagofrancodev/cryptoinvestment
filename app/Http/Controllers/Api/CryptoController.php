<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cryptocurrency;
use App\Models\Portfolio;
use App\Models\PriceSnapshot;
use App\Services\CoinMarketCapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
     * Return historical price snapshots for a cryptocurrency identified by cmc_id.
     *
     * The Snapshot Pattern stores one row per polling interval; this endpoint exposes
     * that local history so the frontend can render time-series charts. We filter by
     * recorded_at using optional from/to parameters, defaulting to the last 24 hours.
     */
    public function history(int $cmcId, Request $request): JsonResponse
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])
            : now()->subDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])
            : now();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->subDay(), $from];
        }

        $snapshots = PriceSnapshot::query()
            ->where('cryptocurrency_id', $crypto->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get([
                'id',
                'cryptocurrency_id',
                'price_usd',
                'percent_change_24h',
                'volume_24h',
                'market_cap',
                'recorded_at',
            ]);

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
                'snapshots' => $snapshots,
            ],
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
