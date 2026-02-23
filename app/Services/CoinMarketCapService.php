<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Single point of contact with the CoinMarketCap API.
 *
 * This service keeps the API key server-side (never exposed to the client), centralises
 * HTTP calls and error handling, and enforces rate limiting via Laravel Cache so we
 * stay within the free tier (e.g. 30 req/min). All CMC requests must go through this class.
 */
class CoinMarketCapService
{
    /**
     * Fetch latest quotes for the given CoinMarketCap IDs.
     *
     * Results are cached for 60 seconds to respect rate limits and avoid redundant
     * API calls when the frontend polls. Cache key is derived from the requested IDs.
     *
     * @param  array<int>  $ids  CMC internal IDs (e.g. 1 for BTC, 1027 for ETH).
     * @return array{data: array<int, array{id: int, name: string, symbol: string, quote: array{USD: array{price: float, volume_24h: float|null, percent_change_24h: float|null, market_cap: float|null}}}}}
     *
     * @throws \RuntimeException When the API request fails or returns a non-2xx status.
     */
    public function getQuotes(array $ids): array
    {
        if (empty($ids)) {
            return ['data' => []];
        }

        $ids = array_values(array_unique($ids));
        $cacheKey = 'cmc_quotes_' . implode('_', $ids);

        return Cache::remember($cacheKey, 60, function () use ($ids): array {
            return $this->fetchQuotesFromApi($ids);
        });
    }

    /**
     * Performs the actual HTTP request to CoinMarketCap. Called only on cache miss.
     *
     * @param  array<int>  $ids
     * @return array{data: array}
     *
     * @throws \RuntimeException On HTTP failure or non-OK response.
     */
    private function fetchQuotesFromApi(array $ids): array
    {
        $baseUrl = config('services.coinmarketcap.base_url');
        $apiKey = config('services.coinmarketcap.key');

        if (empty($apiKey)) {
            Log::error('CoinMarketCap API key is not configured.');
            throw new \RuntimeException('CoinMarketCap API is not configured.');
        }

        $url = $baseUrl . 'cryptocurrency/quotes/latest';
        $response = Http::withHeaders([
            'X-CMC_PRO_API_KEY' => $apiKey,
            'Accept' => 'application/json',
        ])->get($url, [
            'id' => implode(',', $ids),
        ]);

        if ($response->failed()) {
            Log::warning('CoinMarketCap API request failed.', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                'CoinMarketCap API error: ' . $response->status() . ' – ' . $response->body()
            );
        }

        $body = $response->json();
        if (! is_array($body) || ! isset($body['data'])) {
            Log::warning('CoinMarketCap API returned unexpected response.', ['body' => $body]);
            throw new \RuntimeException('CoinMarketCap API returned invalid response.');
        }

        return $body;
    }

    /**
     * Fetch top cryptocurrencies from the listings/latest endpoint.
     *
     * Used by the FetchGlobalCryptos command to populate the cryptocurrencies
     * table so the frontend search can find coins by name or symbol (e.g. PAX, LTC).
     * Not cached so that running the command always gets fresh data.
     *
     * @param  int  $limit  Number of results (1–5000). Default 100 for top 100.
     * @return array{data: array<int, array{id: int, name: string, symbol: string, slug: string}>}
     *
     * @throws \RuntimeException When the API request fails or returns invalid response.
     */
    public function getListingsLatest(int $limit = 100): array
    {
        $baseUrl = config('services.coinmarketcap.base_url');
        $apiKey = config('services.coinmarketcap.key');

        if (empty($apiKey)) {
            Log::error('CoinMarketCap API key is not configured.');
            throw new \RuntimeException('CoinMarketCap API is not configured.');
        }

        $url = $baseUrl . 'cryptocurrency/listings/latest';
        $response = Http::withHeaders([
            'X-CMC_PRO_API_KEY' => $apiKey,
            'Accept' => 'application/json',
        ])->get($url, [
            'start' => 1,
            'limit' => max(1, min(5000, $limit)),
        ]);

        if ($response->failed()) {
            Log::warning('CoinMarketCap listings API request failed.', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                'CoinMarketCap API error: ' . $response->status() . ' – ' . $response->body()
            );
        }

        $body = $response->json();
        if (! is_array($body) || ! isset($body['data'])) {
            Log::warning('CoinMarketCap API returned unexpected response.', ['body' => $body]);
            throw new \RuntimeException('CoinMarketCap API returned invalid response.');
        }

        return $body;
    }

    /**
     * Fetch historical price quotes for a cryptocurrency from CoinMarketCap v2 API.
     *
     * Uses /v2/cryptocurrency/quotes/historical with time_start and time_end derived
     * from the given range (7d, 30d, 1y). Response is mapped to a Chart.js–friendly
     * structure: array of { recorded_at, price_usd } (and optional quote fields).
     *
     * @param  int  $cmcId  CoinMarketCap internal ID (e.g. 1 for BTC, 1027 for ETH).
     * @param  string  $range  Range identifier: '7d', '30d', or '1y'.
     * @return array{labels: array<string>, data: array<float>, snapshots: array<int, array{recorded_at: string, price_usd: float}>}
     *
     * @throws \RuntimeException When the API request fails or returns invalid response.
     */
    public function getHistory(int $cmcId, string $range): array
    {
        $apiKey = config('services.coinmarketcap.key');
        if (empty($apiKey)) {
            Log::error('CoinMarketCap API key is not configured.');
            throw new \RuntimeException('CoinMarketCap API is not configured.');
        }

        [$timeStart, $timeEnd] = $this->rangeToTimeInterval($range);
        $interval = $this->rangeToInterval($range);
        $baseUrlV2 = config('services.coinmarketcap.base_url_v2');
        $url = $baseUrlV2 . 'cryptocurrency/quotes/historical';

        $params = [
            'id' => $cmcId,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'interval' => $interval,
        ];

        $response = Http::withHeaders([
            'X-CMC_PRO_API_KEY' => $apiKey,
            'Accept' => 'application/json',
        ])->get($url, $params);

        if ($response->failed()) {
            Log::warning('CoinMarketCap historical API request failed.', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                'CoinMarketCap API error: ' . $response->status() . ' – ' . $response->body()
            );
        }

        $body = $response->json();
        return $this->mapHistoryResponseForChart($body, $range);
    }

    /**
     * Convert a range string (7d, 30d, 1y, 24h) to time_start and time_end in ISO 8601.
     * time_end is always now(); time_start is that minus the range so the API returns
     * the correct interval (not always last 24h).
     *
     * @return array{0: string, 1: string} [time_start, time_end]
     */
    private function rangeToTimeInterval(string $range): array
    {
        $timeEnd = now();
        $timeStart = match (strtolower($range)) {
            '24h' => $timeEnd->copy()->subDay(),
            '7d' => $timeEnd->copy()->subDays(7),
            '30d' => $timeEnd->copy()->subDays(30),
            '1y' => $timeEnd->copy()->subYear(),
            default => $timeEnd->copy()->subDays(7),
        };

        return [
            $timeStart->toIso8601String(),
            $timeEnd->toIso8601String(),
        ];
    }

    /**
     * Interval for v2 historical API: fewer, evenly spaced points per range to avoid saturation.
     */
    private function rangeToInterval(string $range): string
    {
        return match (strtolower($range)) {
            '24h' => 'hourly',
            '7d' => 'hourly',
            '30d' => 'daily',
            '1y' => 'daily',
            default => 'hourly',
        };
    }

    /**
     * Map CoinMarketCap v2 historical response to Chart.js–friendly format.
     *
     * Expected CMC response shape: data.quotes[] with timestamp and quote.USD.price.
     * We output: snapshots (for backward compatibility with frontend) and optionally
     * labels + data for direct Chart.js use.
     *
     * @param  array<string, mixed>  $body
     * @return array{labels: array<string>, data: array<float>, snapshots: array<int, array{recorded_at: string, price_usd: float}>}
     */
    private function mapHistoryResponseForChart(array $body, string $range): array
    {
        $snapshots = [];
        $labels = [];
        $data = [];

        $rawData = $body['data'] ?? [];
        $quotes = $rawData['quotes'] ?? [];
        if (! is_array($quotes) && is_array($rawData)) {
            foreach ($rawData as $item) {
                if (is_array($item) && isset($item['quotes']) && is_array($item['quotes'])) {
                    $quotes = $item['quotes'];
                    break;
                }
            }
        }
        if (! is_array($quotes) || empty($quotes)) {
            return ['labels' => [], 'data' => [], 'snapshots' => []];
        }

        $rangeNorm = strtolower($range);
        foreach ($quotes as $quote) {
            $timestamp = $quote['timestamp'] ?? null;
            $usd = $quote['quote']['USD'] ?? null;
            if (! $timestamp || ! is_array($usd)) {
                continue;
            }
            $price = isset($usd['price']) ? (float) $usd['price'] : 0;
            $recordedAt = is_string($timestamp) ? $timestamp : date('c', (int) $timestamp);

            $snapshots[] = [
                'recorded_at' => $recordedAt,
                'price_usd' => $price,
            ];
            $labels[] = $recordedAt;
            $data[] = $price;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'snapshots' => $snapshots,
        ];
    }
}
