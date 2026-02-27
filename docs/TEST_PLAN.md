# Plan de Pruebas y Validación (QA)

Este documento detalla el cumplimiento de las **3 pruebas requeridas** en el reto técnico CryptoInvestment.

---

## 1. Adaptabilidad en diferentes resoluciones

| Caso | Pasos | Resultado esperado |
|------|-------|--------------------|
| **Desktop (>768px)** | Abrir la app en ventana amplia | La tabla y el gráfico se muestran en la misma página. Al seleccionar una moneda, el gráfico de historial aparece inline. Los Hero Cards muestran las monedas del portafolio en fila. |
| **Tablet** | Reducir viewport a ~768px | Los Hero Cards permiten scroll horizontal (`overflow-x-auto`). La tabla mantiene scroll horizontal si es necesario. El gráfico se adapta al ancho disponible. |
| **Mobile (Portrait)** | Usar vista móvil (<768px) | La tabla es legible con scroll horizontal. Al hacer clic en una fila, se abre un **modal** con el gráfico a pantalla completa. El botón "Comparar Portafolio" abre el modal con el gráfico comparativo. |
| **Mobile (Landscape)** | Rotar dispositivo a horizontal | El modal del gráfico ocupa todo el ancho, títulos compactos, botón X flotante, sin scroll innecesario. El gráfico se ajusta a la altura disponible. |

**Verificación técnica:** La vista incluye `<meta name="viewport" content="width=device-width, initial-scale=1">` y clases Tailwind responsivas (`sm:`, `md:`, `max-md:`, `overflow-x-auto`).

---

## 2. Actualización dinámica (sin recarga de página)

| Caso | Pasos | Resultado esperado |
|------|-------|--------------------|
| **Polling automático** | Esperar 30 segundos con el portafolio cargado | El indicador de estado (ej. "Sincronizado", "Actualizado") o la hora de "Última actualización" cambia sin recargar. Los precios de la tabla se actualizan vía Fetch. |
| **Cambio de rango del gráfico** | Clic en "24h", "7d", "30d" o "1y" | El gráfico se actualiza con los datos del nuevo rango **sin recargar la página**. |
| **Agregar criptomoneda** | Clic en "Agregar cripto" → buscar → seleccionar | La tabla incluye la nueva moneda inmediatamente (AJAX/Fetch). No hay recarga. |
| **Quitar criptomoneda** | Clic en eliminar de una fila | La fila desaparece de la tabla sin recarga. |

**Verificación técnica:** El HTML/JS contiene `setInterval` con `fetchData` para polling periódico.

---

## 3. Trabajo en tiempo real con monedas

| Caso | Pasos | Resultado esperado |
|------|-------|--------------------|
| **Consistencia con CoinMarketCap** | Comparar precio mostrado con [coinmarketcap.com](https://coinmarketcap.com) | Margen de diferencia razonable (≤1–2 min por caché y rate limiting). |
| **Snapshots en base de datos** | Tras varios ciclos de polling, revisar tabla `price_snapshots` | Se generan registros nuevos (cada 30–60 s según polling y caché). Campos: `price_usd`, `percent_change_24h`, `volume_24h`, `recorded_at`. |
| **Endpoint `/api/crypto/data`** | `GET /api/crypto/data` | Respuesta JSON con `success: true` y array `data` con precios, cambio %, volumen, market cap. |

**Verificación técnica:** El endpoint `/api/crypto/data` retorna la estructura esperada. El `CoinMarketCapService` está configurado en `config/services.php` y es el único punto de contacto con la API externa.

---

## Ejecución de tests automatizados

```bash
php artisan test
```

Los tests en `tests/Feature/TechnicalRequirementsTest.php` y `tests/Feature/CryptoApiTest.php` validan automáticamente la infraestructura necesaria para estas pruebas (meta viewport, clases responsivas, polling, estructura JSON del API).
