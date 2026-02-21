# CryptoInvestment

Aplicación web **single page** para seguir un portafolio de criptomonedas: cotizaciones en tiempo (casi) real, historial de precios con gráficos y búsqueda de activos. Backend en Laravel; frontend con Blade, Vanilla JS, Tailwind CSS y Chart.js. API de CoinMarketCap como fuente de datos (proxy desde el servidor por CORS y seguridad).

**Endpoints validados inicialmente con Postman siguiendo los requerimientos del reto.** Colección disponible en `docs/postman_collection.json`.

---

## Requisitos

- PHP 8.2+
- Composer
- Extensiones PHP: pdo_sqlite (o MySQL), json, mbstring, openssl
- Cuenta en [CoinMarketCap](https://coinmarketcap.com/api/) (plan gratuito) para obtener `COINMARKETCAP_API_KEY`

---

## Instalación

1. **Clonar y dependencias**
   ```bash
   git clone <repo>
   cd cryptoinvestment
   composer install
   ```

2. **Entorno**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Editar `.env` y configurar al menos:
   - `COINMARKETCAP_API_KEY=` con tu API key de CoinMarketCap.

3. **Base de datos**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. **Datos globales para búsqueda**  
   Cargar las criptomonedas desde CoinMarketCap para que el buscador y el portafolio funcionen:
   ```bash
   php artisan crypto:fetch-global
   ```
   Opcional: `php artisan crypto:fetch-global --limit=200`

5. **Servidor**
   ```bash
   php artisan serve
   ```
   Abrir `http://localhost:8000`.

---

## Estrategia de ramas

- **`main`**: rama de producción; solo se fusionan features completos y probados.
- **`feature/api-integration`**: rama de desarrollo para la integración con la API y la lógica del portafolio.

Se trabaja en ramas de feature y se hace merge a `main` cuando la funcionalidad está lista.

---

## API (resumen)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/crypto/data` | Datos del portafolio con cotizaciones actuales (y persistencia de snapshots) |
| GET | `/api/crypto/search?q=` | Búsqueda de criptomonedas por nombre o símbolo |
| POST | `/api/portfolio` | Añadir crypto al portafolio (body: `cryptocurrency_id`) |
| DELETE | `/api/portfolio/{id}` | Quitar del portafolio por ID de entrada |
| GET | `/api/crypto/history/{cmc_id}?from=&to=` | Historial de precios (snapshots) por rango de fechas |

Todas las respuestas JSON siguen: `success`, `data`, `message` (opcional).  
Para probar los endpoints se puede importar la colección Postman en `docs/postman_collection.json`.

---

## Checklist de pruebas

- **Adaptabilidad (responsive)**: La interfaz se probó en móvil, tablet y escritorio; la tabla y el gráfico se adaptan al viewport.
- **Tiempo real (polling)**: Se verificó que el frontend actualiza las cotizaciones periódicamente (polling cada 60 s) sin recargar la página.

---

## Tests

```bash
php artisan test
```

Incluye un test de feature que comprueba que `GET /api/crypto/data` responde 200 y devuelve la estructura JSON correcta (`success`, `data`). Ver `tests/Feature/CryptoApiTest.php`.

---

## Documentación adicional

- **Análisis y decisiones**: `ANALISIS.md`
- **Colección Postman**: `docs/postman_collection.json`

---

## Licencia

MIT.
