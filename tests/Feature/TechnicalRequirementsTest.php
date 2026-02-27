<?php

namespace Tests\Feature;

use App\Services\CoinMarketCapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the 3 "Pruebas" required by the technical challenge.
 *
 * These validate the infrastructure and markup needed for:
 * 1. Adaptabilidad en diferentes resoluciones
 * 2. Actualización dinámica de datos según necesidad del cliente
 * 3. Trabajo en tiempo real con monedas
 */
class TechnicalRequirementsTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Prueba 1 — Adaptabilidad en diferentes resoluciones
     *
     * Verifies that the view contains:
     * - Meta viewport for responsive scaling
     * - Tailwind responsive classes (sm:, md:, max-md:, overflow-x-auto)
     */
    public function test_adaptabilidad_view_contains_viewport_and_responsive_markup(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        $html = $response->getContent();

        // Meta viewport is essential for mobile adaptability
        $this->assertStringContainsString('viewport', $html);
        $this->assertStringContainsString('width=device-width', $html);

        // Responsive Tailwind classes (desktop/tablet/mobile breakpoints)
        $this->assertStringContainsString('sm:', $html);
        $this->assertStringContainsString('md:', $html);
        $this->assertStringContainsString('max-md:', $html);

        // Scroll horizontal for table on small screens
        $this->assertStringContainsString('overflow-x-auto', $html);

        // Modal for mobile chart (chart-modal)
        $this->assertStringContainsString('chart-modal', $html);
    }

    /**
     * Prueba 2 — Actualización dinámica (sin recarga)
     *
     * Verifies that the page contains polling logic (setInterval + fetchData)
     * so data updates automatically without page reload.
     */
    public function test_actualizacion_dinamica_view_contains_polling_logic(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        $html = $response->getContent();

        // Polling mechanism
        $this->assertStringContainsString('setInterval', $html);
        $this->assertStringContainsString('fetchData', $html);

        // Chart range buttons (24h, 7d, 30d, 1y) for dynamic chart updates
        $this->assertStringContainsString('data-range', $html);
    }

    /**
     * Prueba 3 — Trabajo en tiempo real
     *
     * Verifies that:
     * - The /api/crypto/data endpoint returns valid structure with price data
     * - CoinMarketCapService is configured and resolvable
     */
    public function test_tiempo_real_endpoint_returns_valid_structure(): void
    {
        $response = $this->getJson('/api/crypto/data');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
        $response->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);

        // When portfolio has items, each must have price-related keys
        if (count($data) > 0) {
            $first = $data[0];
            $this->assertArrayHasKey('price_usd', $first);
            $this->assertArrayHasKey('percent_change_24h', $first);
            $this->assertArrayHasKey('volume_24h', $first);
        }
    }

    /**
     * Prueba 3 (complementaria) — CoinMarketCapService está configurado
     */
    public function test_tiempo_real_coinmarketcap_service_is_configured(): void
    {
        $this->assertTrue(
            config()->has('services.coinmarketcap'),
            'config/services.php must define coinmarketcap key and base_url'
        );

        $config = config('services.coinmarketcap');
        $this->assertArrayHasKey('key', $config);
        $this->assertArrayHasKey('base_url', $config);

        // Service must be resolvable from container
        $service = app(CoinMarketCapService::class);
        $this->assertInstanceOf(CoinMarketCapService::class, $service);
    }
}
