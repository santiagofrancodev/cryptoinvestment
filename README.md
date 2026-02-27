## 💎UPDATE: Se ha incorporado una suite de pruebas automatizadas y plan de validación técnica según los requisitos del reto.


# CryptoInvestment

Aplicación web **single page** para seguir un portafolio de criptomonedas: cotizaciones en tiempo (casi) real, historial de precios con gráficos y búsqueda de activos. Backend en Laravel; frontend con Blade, Vanilla JS, Tailwind CSS y Chart.js. API de CoinMarketCap como fuente de datos (proxy desde el servidor por CORS y seguridad).

**Endpoints validados inicialmente con Postman siguiendo los requerimientos del reto.** Colección disponible en `docs/postman_collection.json`.

---

## Requisitos

- PHP 8.2+
- Composer
- Node.js 20+ (para compilar assets con Vite)
- Extensiones PHP: pdo_sqlite (o MySQL), json, mbstring, openssl
- Cuenta en [CoinMarketCap](https://coinmarketcap.com/api/) (plan gratuito) para obtener `COINMARKETCAP_API_KEY`

---

## Instalación

1. **Clonar y dependencias**
   ```bash
   git clone <repo>
   cd cryptoinvestment
   composer install
   npm install
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

5. **Assets (Vite)**
   ```bash
   npm run build
   ```
   Para desarrollo con hot reload: `npm run dev`

6. **Servidor**
   ```bash
   php artisan serve
   ```
   Abrir `http://localhost:8000`.

---

## Pruebas

### Tests automatizados (PHPUnit)

```bash
php artisan test
```

Incluye:

| Archivo | Descripción |
|---------|-------------|
| `tests/Feature/TechnicalRequirementsTest.php` | Valida las 3 pruebas del reto: adaptabilidad (viewport, clases responsive), actualización dinámica (setInterval, fetchData), trabajo en tiempo real (endpoint `/api/crypto/data`, `CoinMarketCapService`) |
| `tests/Feature/CryptoApiTest.php` | Valida estructura JSON de la API |
| `tests/Feature/ExampleTest.php` | Smoke test: respuesta 200 en `/` |

**Nota:** Los tests usan `withoutVite()`, por lo que no requieren compilar assets previamente.

### Checklist manual (QA)

Para validar manualmente las 3 pruebas del reto (adaptabilidad, actualización dinámica, tiempo real), sigue la guía paso a paso en:

**[`docs/TEST_PLAN.md`](docs/TEST_PLAN.md)**

Incluye casos para desktop, tablet, móvil portrait/landscape, polling, cambio de rango del gráfico y verificación de snapshots en base de datos.

---

## Diagrama de arquitectura

El diagrama de alto nivel del sistema (Client Layer, Backend Layer, Data & External API, patrones implementados) está en:

**[`docs/ArchitectureDiagram.drawio.svg`](docs/ArchitectureDiagram.drawio.svg)**

### Cómo visualizarlo

| Método | Instrucción |
|--------|-------------|
| **En el navegador** | Abre el archivo SVG directamente (doble clic o arrástralo a una pestaña). GitHub lo renderiza automáticamente en el repo. |
| **Editar / exportar** | Ábrelo en [diagrams.net](https://app.diagrams.net/) (Draw.io) → *File → Open from → Device* y selecciona el `.drawio.svg`. |
| **En VS Code** | Con la extensión "Draw.io Integration" puedes previsualizarlo y editarlo en el editor. |

El formato `.drawio.svg` combina la visualización estándar SVG con los datos editables de Draw.io.

---

## Cambios recientes (Refactor de Pruebas)

- **Suite de pruebas híbrida:** Tests automatizados en PHPUnit + plan de pruebas manuales en `docs/TEST_PLAN.md`.
- **`TechnicalRequirementsTest`:** 4 tests que validan viewport, clases responsivas, lógica de polling y configuración del API.
- **`withoutVite()` en TestCase:** Permite ejecutar tests sin compilar assets.
- **Diagrama:** `docs/ArchitectureDiagram.drawio.svg` (solo este; `.codeviz` y diagramas .drawio de prueba excluidos).
- **[Análisis](docs/ANALISIS.md):** Sección 13 documentando la estrategia de pruebas.

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
| GET | `/api/crypto/history-bulk?ids=&from=&to=` | Historial bulk para comparar varias monedas |

Todas las respuestas JSON siguen: `success`, `data`, `message` (opcional).  
Para probar los endpoints se puede importar la colección Postman en `docs/postman_collection.json`.

---

## Documentación adicional

| Documento | Descripción |
|-----------|-------------|
| [docs/ANALISIS.md](docs/ANALISIS.md) | Análisis de requisitos, decisiones de arquitectura y reglas de código |
| `docs/TEST_PLAN.md` | Plan de pruebas manuales para las 3 pruebas del reto |
| `docs/ArchitectureDiagram.drawio.svg` | Diagrama de arquitectura (SVG editable en Draw.io) |
| `docs/postman_collection.json` | Colección Postman para los endpoints |

---

## Licencia

MIT.
