# Análisis del Proyecto — CryptoInvestment

**Prueba Técnica IGNIWEB SAS**

Documento de análisis y decisiones de arquitectura para dar inicio a la **Tarea 1** del proyecto. Sirve como referencia única de contexto, restricciones y criterios de implementación.

---

## 1. Requisitos Funcionales (RF)

Identificados a partir del enunciado del reto:

| Id | Requisito | Origen |
|----|-----------|--------|
| RF-01 | Permitir al usuario seguir el rendimiento de un **conjunto personalizado** de criptomonedas a selección | Reto |
| RF-02 | Mostrar **precios actualizados** de las criptomonedas seleccionadas | Reto |
| RF-03 | Mostrar **cambios porcentuales** (variación 24h) | Reto |
| RF-04 | Mostrar **volumen del mercado** | Reto |
| RF-05 | Proporcionar una **visión consolidada** del historial de precios (vs. hojas de cálculo y sitios dispersos) | Reto |
| RF-06 | **Persistencia de datos en el tiempo** — los socios la consideran de vital importancia | Reto |
| RF-07 | Permitir **verificaciones de líneas de tiempo** en un rango de tiempo configurable | Reto |
| RF-08 | **Visualización desde diferentes dispositivos** (desktop, tablet, móvil) | Reto |
| RF-09 | Sistema de **una sola hoja** (single page) — sin navegación a otras pantallas | Reto |
| RF-10 | **Cambios dinámicos sin recarga** de la página | Reto |
| RF-11 | Implementar lógica para **buscar** criptomonedas | Tareas |
| RF-12 | Permitir **seleccionar** criptomonedas para agregar al portafolio | Tareas |
| RF-13 | **Integrar la API de CoinMarketCap** para obtener cotizaciones | Tareas |
| RF-14 | **Actualización automática** de datos (polling desde el cliente) | Tareas |

---

## 2. Requisitos No Funcionales (RNF)

| Id | Requisito | Origen |
|----|-----------|--------|
| RNF-01 | **Lenguaje de programación:** PHP (Laravel) en backend; JavaScript en el lado del cliente | Detalles Técnicos |
| RNF-02 | **API:** CoinMarketCap API utilizando claves API gratuitas | Detalles Técnicos |
| RNF-03 | **Visualización de gráficos:** Utilizar una biblioteca JavaScript como Chart.js | Detalles Técnicos |
| RNF-04 | **Actualización de datos:** Funcionalidades en JavaScript para solicitudes periódicas y actualización en el cliente | Detalles Técnicos |
| RNF-05 | **Adaptabilidad en diferentes resoluciones** — la interfaz debe funcionar en desktop, tablet y móvil | Pruebas |
| RNF-06 | **Actualización dinámica según necesidad del cliente** — sin recargas; el usuario controla rangos y vistas | Pruebas |
| RNF-07 | **Trabajo en tiempo real con monedas** — cotizaciones actualizadas periódicamente | Pruebas |
| RNF-08 | **Simplicidad** — evitar soluciones muy complejas; prioridad a claridad y cumplimiento | Reto |
| RNF-09 | **Control de versiones:** Git con rama principal y ramas secundarias; vinculado a GitHub | Tareas |

---

## 3. Otros detalles técnicos (análisis del reto)

Consideraciones adicionales derivadas del análisis del párrafo inicial y del contexto técnico:

| Aspecto | Decisión | Justificación |
|---------|----------|---------------|
| **Proxy Laravel** | Laravel actúa como proxy entre el cliente y CoinMarketCap | CORS bloquea peticiones directas desde el navegador; la API key no debe exponerse nunca en el frontend. |
| **Snapshot Pattern** | Persistir cotizaciones en tabla `price_snapshots` en cada polling | La API gratuita de CMC no provee datos históricos; el historial se construye con el tiempo de forma local. |
| **Rate limiting y caché** | Cachear respuestas de CMC 55–60 s; respetar ~30 req/min del plan gratuito | Evitar 429 (Too Many Requests); reducir llamadas redundantes. |
| **Service Layer** | Una sola clase (`CoinMarketCapService`) como punto de contacto con la API externa | Centralizar key, headers, errores HTTP y fallbacks; no dispersar lógica de CMC en controllers. |
| **Fallback a snapshots** | Si la API falla o devuelve 429: retornar último snapshot de la DB | Garantizar disponibilidad aunque CMC no responda. |
| **Navegación adaptativa** | Desktop: gráfico inline; Móvil: gráfico en modal a pantalla completa | Optimizar uso del espacio en pantallas pequeñas; mejorar UX en landscape. |
| **Export a PDF** | Generar informe visual (PDF) preservando modo oscuro | Permitir compartir o archivar el portafolio sin perder el estilo visual. |
| **Comparación normalizada** | Gráfico comparativo de rendimiento % entre varias monedas del portafolio | Visión consolidada del rendimiento relativo (RF-05). |
| **Suite de pruebas híbrida** | PHPUnit para infraestructura + plan manual (`docs/TEST_PLAN.md`) para UX | Validar las 3 pruebas del reto de forma automatizable y reproducible. |
| **HTTPS forzado en producción** | `URL::forceScheme('https')` en `AppServiceProvider` | Railway y CDN sirven por HTTPS; evita mixed content y carga fallida de assets. |
| **Vite + manifest** | Build de assets con Vite; manifest para resolver rutas en producción | Tailwind y JS compilados; URLs correctas en deploy. |

---

## 4. Objetivo del Proyecto

Aplicación web **single page** que permite:

- Consultar cotizaciones de criptomonedas en tiempo (casi) real vía API de CoinMarketCap.
- Mantener un **portafolio** de criptomonedas a seguir.
- Visualizar **historial de precios** en un gráfico de líneas con selección de rango de fechas.

Tiempo estimado de desarrollo: **5 horas**. Prioridad: claridad y cumplimiento de requisitos sobre complejidad.

---

## 5. Restricciones y Problema del Historial (Decisión Crítica)

### Limitación de la API

La **API gratuita de CoinMarketCap no provee datos históricos** ("No historical data"). No se contempla upgrade al plan de pago para esta prueba.

### Solución adoptada: Motor de persistencia propio (Snapshot Pattern)

| Aspecto | Decisión |
|--------|----------|
| **Qué** | En cada polling del frontend (cada 60 s), el servidor guarda un registro en la tabla `price_snapshots`. |
| **Para qué** | Construir un historial propio en base de datos local con el tiempo. |
| **Resultado** | Cumplir el requerimiento de "verificación de líneas de tiempo" sin depender de una API de pago. |
| **Gráfico** | El eje X del gráfico Chart.js usa el campo `recorded_at` de `price_snapshots`. |

Esta decisión debe quedar reflejada en comentarios del código donde sea relevante.

---

## 6. Por qué Laravel actúa como Proxy (Decisión Crítica)

1. **CORS**: CoinMarketCap bloquea peticiones directas desde el navegador.
2. **Seguridad**: La API key no debe aparecer nunca en código frontend.
3. **Snapshot**: Permite interceptar la respuesta, guardar el snapshot y luego enviar datos al cliente.
4. **Rate limiting**: Centraliza el manejo del límite del plan gratuito (30 req/min).

**Regla**: No generar código que llame a CoinMarketCap desde JavaScript del cliente.

---

## 7. Stack Tecnológico

| Capa | Tecnología |
|------|------------|
| Backend | PHP 8.x + Laravel 11 |
| Frontend | Blade (una sola vista) + Vanilla JS (Fetch API) |
| Estilos | Tailwind CSS vía CDN |
| Gráficos | Chart.js vía CDN |
| Base de datos | MySQL + Eloquent ORM |
| API externa | CoinMarketCap REST API v1 (plan gratuito) |
| Cache | Laravel Cache driver (file) para rate limiting |

---

## 8. Arquitectura: MVC + Service Pattern

### Responsabilidades

| Componente | Responsabilidad |
|-----------|-----------------|
| **Service** | Única clase que habla con CoinMarketCap. Maneja key, headers y errores HTTP. |
| **Controller** | Recibe request → llama al Service → guarda snapshot → retorna JSON. |
| **Model** | Solo relaciones Eloquent y scopes de consulta. Sin lógica de negocio. |
| **Blade** | Una sola vista. Todo el dinamismo vía Vanilla JS y `fetch()`. |

### Lo que NO se implementa (y por qué)

- **Repository Pattern**: Fuera del alcance; decisión consciente para evitar sobre-ingeniería en el tiempo disponible (5 h).
- **Autenticación**: Fuera del alcance de la prueba.
- **Livewire / Inertia**: Añaden complejidad innecesaria para el objetivo.
- **jQuery**: Innecesario con Vanilla JS y Fetch API nativa.

---

## 9. Estructura de Archivos Objetivo

```
app/
├── Http/Controllers/
│   ├── CryptoController.php       # Búsqueda y cotizaciones en tiempo real
│   └── PortfolioController.php    # CRUD del portafolio
├── Models/
│   ├── Cryptocurrency.php         # Info estática: name, symbol, cmc_id
│   ├── Portfolio.php              # Cryptos que el usuario quiere seguir
│   └── PriceSnapshot.php          # Corazón del historial propio
└── Services/
    └── CoinMarketCapService.php   # Único punto de contacto con API externa

resources/views/
└── app.blade.php                  # Single Page — toda la UI aquí, sin recarga

routes/
├── web.php                        # GET / → app.blade.php
└── api.php                        # Todos los endpoints JSON

database/migrations/
├── create_cryptocurrencies_table.php
├── create_portfolios_table.php
└── create_price_snapshots_table.php
```

---

## 10. Esquema de Base de Datos

### `cryptocurrencies`

| Columna | Tipo | Notas |
|---------|------|--------|
| id | bigint PK | |
| cmc_id | int, unique | ID interno CoinMarketCap, para queries exactas |
| name | string | ej: "Bitcoin" |
| symbol | string | ej: "BTC" |
| slug | string | ej: "bitcoin" |
| timestamps | | |

### `portfolios`

| Columna | Tipo | Notas |
|---------|------|--------|
| id | bigint PK | |
| cryptocurrency_id | FK → cryptocurrencies.id | |
| timestamps | | |

### `price_snapshots` (corazón del historial)

| Columna | Tipo | Notas |
|---------|------|--------|
| id | bigint PK | |
| cryptocurrency_id | FK → cryptocurrencies.id | |
| price_usd | decimal(18,8) | |
| percent_change_24h | decimal(8,4) | |
| volume_24h | decimal(20,2) | |
| market_cap | decimal(20,2) | |
| recorded_at | timestamp | Eje X del gráfico Chart.js; **indexado** |
| timestamps | | |

---

## 11. Endpoints API Internos

| Método | Ruta | Acción |
|--------|------|--------|
| GET | /api/crypto/search | Buscar cryptos para agregar al portafolio |
| POST | /api/portfolio | Agregar crypto al portafolio |
| DELETE | /api/portfolio/{id} | Quitar crypto del portafolio (por portfolio.id) |
| GET | /api/crypto/data | Cotizaciones del portafolio + guardar snapshot |
| GET | /api/crypto/history/{cmc_id} | Snapshots filtrados por `?from=` y `?to=` |

Todas las respuestas JSON siguen:

```json
{
  "success": true | false,
  "data": { ... } | [ ... ],
  "message": "string opcional"
}
```

---

## 12. Flujo de Datos (Single Page)

1. **Carga inicial**  
   Navegador → GET `/` → Laravel sirve `app.blade.php` con datos del portafolio desde DB.

2. **Polling automático (cada 60 s)**  
   - JS `setInterval` → `fetch('/api/crypto/data')`  
   - CryptoController llama a CoinMarketCapService → Service llama a CoinMarketCap API  
   - Controller guarda `PriceSnapshot` en DB (aquí se construye el historial)  
   - Controller retorna JSON al navegador  
   - Vanilla JS actualiza tabla y Chart.js sin recargar  

3. **Visualización de historial**  
   - Usuario elige crypto + rango de fechas  
   - `fetch('/api/crypto/history/{cmc_id}?from=&to=')`  
   - Controller consulta `price_snapshots` filtrado por `recorded_at`  
   - Chart.js renderiza línea de tiempo con `recorded_at` en el eje X  

---

## 13. Reglas de Código Resumidas

### PHP / Laravel

- Comentarios en inglés técnico.
- PascalCase clases, camelCase métodos/variables, snake_case tablas/columnas.
- Try/catch en todo método del Service que llame a la API externa.
- Si la API falla o devuelve 429: retornar último snapshot de la DB como fallback.
- Usar `config('services.coinmarketcap.key')`; no usar `env()` directo fuera de `config/`.
- Validar inputs en Controllers antes de pasarlos al Service.

### Rate limiting (plan gratuito ≈ 30 req/min)

- `setInterval` en frontend: mínimo 60 000 ms (60 segundos).
- Cachear última respuesta válida por 55 segundos (Laravel Cache).
- Si llega un request y hay cache vigente: retornar cache sin llamar a la API.
- En error 429: retornar último registro de `price_snapshots` con flag `"cached": true`.

### JavaScript (Vanilla JS)

- Sin jQuery ni frameworks pesados (Alpine.js, React, etc.).
- URLs de API como constantes al inicio del script, no hardcodeadas inline.
- Indicador visual "Actualizando..." durante cada fetch (sin bloquear la UI).
- Errores de fetch: mostrar mensaje en el DOM; no usar `alert()`.
- Chart.js: destruir instancia anterior antes de crear una nueva (evitar fugas de memoria).
- Eje X del gráfico: usar `recorded_at` formateado, no índices numéricos.

### Git

- Rama principal: `main` (solo merges de features completos).
- Ramas de desarrollo: `feature/backend-api`, `feature/frontend-ui`, `feature/charts`.
- Commits: `feat: descripción` / `fix: descripción` / `docs: descripción`.
- No commitear `.env`; comprobar que está en `.gitignore` antes de cada push.

---

## 14. Prioridades ante Conflictos

1. API key nunca expuesta al cliente.  
2. Single Page sin recargas (requerimiento explícito).  
3. Historial funcional vía snapshots (solución al límite del plan gratuito).  
4. Código legible y comentado (criterio de evaluación).  
5. Diseño responsive en móvil, tablet y desktop.

---

## 15. Tarea 1 — Punto de Partida

Con este análisis se puede iniciar la **Tarea 1** del proyecto con el siguiente orden sugerido:

1. **Configuración y entorno**  
   - Servicio CoinMarketCap en `config/services.php`.  
   - Variables en `.env` (key, base URL).  

2. **Base de datos**  
   - Crear migraciones: `cryptocurrencies`, `portfolios`, `price_snapshots`.  
   - Ejecutar migraciones.  

3. **Modelos**  
   - `Cryptocurrency`, `Portfolio`, `PriceSnapshot` con relaciones y scopes mínimos.  

4. **Service**  
   - `CoinMarketCapService`: búsqueda y quotes con try/catch, uso de `config()`, manejo de 429 y fallback a último snapshot.  

5. **Controllers y rutas**  
   - Rutas en `api.php` según la tabla de endpoints.  
   - `CryptoController` y `PortfolioController` delegando en el Service y persistiendo snapshots donde corresponda.  

6. **Vista y frontend**  
   - Una sola vista con Vanilla JS, Tailwind CSS y Chart.js; polling 60 s; constantes de API; manejo de errores en DOM.  

Este documento (`ANALISIS.md`) es la referencia para mantener coherencia con las decisiones de arquitectura durante toda la implementación.

---

## 16. Suite de Pruebas (Pruebas del Reto)

Se implementó una **suite de pruebas híbrida** para cumplir con el apartado de "Pruebas" del reto:

1. **Tests automatizados (PHPUnit):**  
   - `tests/Feature/TechnicalRequirementsTest.php` valida la infraestructura para las 3 pruebas: viewport y clases responsivas, lógica de polling (`setInterval`/`fetchData`), estructura JSON del endpoint `/api/crypto/data` y configuración del `CoinMarketCapService`.  
   - `tests/Feature/CryptoApiTest.php` valida la estructura de datos retornada por la API.

2. **Plan de pruebas manuales:**  
   - [docs/TEST_PLAN.md](TEST_PLAN.md) es una guía paso a paso para que el evaluador valide manualmente la adaptabilidad en distintas resoluciones, la actualización dinámica sin recarga y el trabajo en tiempo real con monedas.

Ejecución: `php artisan test`
