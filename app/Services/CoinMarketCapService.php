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
}
