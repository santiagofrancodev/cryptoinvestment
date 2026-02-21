<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CryptoApiTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Verifica que el endpoint GET /api/crypto/data responde 200
     * y tiene la estructura JSON esperada (success, data).
     * Cubre el punto de Pruebas del reto.
     */
    public function test_crypto_data_endpoint_returns_200_and_valid_structure(): void
    {
        $response = $this->getJson('/api/crypto/data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
        $response->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    /**
     * Cuando el portafolio tiene datos, cada item de data tiene la estructura esperada.
     */
    public function test_crypto_data_items_have_expected_keys_when_not_empty(): void
    {
        $response = $this->getJson('/api/crypto/data');

        $response->assertStatus(200);
        $data = $response->json('data');

        if (count($data) > 0) {
            $first = $data[0];
            $this->assertArrayHasKey('portfolio_id', $first);
            $this->assertArrayHasKey('cryptocurrency_id', $first);
            $this->assertArrayHasKey('cmc_id', $first);
            $this->assertArrayHasKey('name', $first);
            $this->assertArrayHasKey('symbol', $first);
            $this->assertArrayHasKey('slug', $first);
            $this->assertArrayHasKey('price_usd', $first);
            $this->assertArrayHasKey('percent_change_24h', $first);
            $this->assertArrayHasKey('volume_24h', $first);
            $this->assertArrayHasKey('market_cap', $first);
        }
    }
}
