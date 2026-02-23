<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema de Gestión de Activos Crypto - CryptoInvestment</title>

    @vite(['resources/css/app.css'])
    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js for price history line chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" defer></script>
</head>
<body class="min-h-screen text-slate-100 antialiased">
    <div id="report-content" class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Sistema de Gestión de Activos Crypto - CryptoInvestment</h1>
            <p class="mt-1 text-sm text-slate-400">Cotizaciones en tiempo real · Snapshots automáticos</p>
        </header>

        <!-- Portfolio header + add button -->
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-200">Portafolio</h2>
            <button
                id="add-crypto-btn"
                type="button"
                class="no-print inline-flex items-center gap-1.5 rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-medium text-emerald-950 shadow shadow-emerald-500/40 hover:bg-emerald-400"
            >
                <span class="text-base leading-none">+</span>
                <span>Agregar cripto</span>
            </button>
        </div>

        <!-- Last update + status + Report + Compare -->
        <div class="mb-4 flex flex-wrap items-center gap-4 text-sm">
            <span id="last-update" class="text-slate-400">Última actualización: —</span>
            <span id="status" class="rounded-full bg-slate-700/50 px-3 py-1 text-slate-300" aria-live="polite">Cargando…</span>
            <button type="button" id="compare-portfolio-btn-top" class="no-print hidden flex items-center gap-2 rounded-lg bg-slate-700/80 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-600 transition-colors" title="Ver rendimiento % de todo el portafolio">
                <svg class="h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <span>Comparar Portafolio</span>
            </button>
            <button type="button" id="report-btn" class="no-print inline-flex items-center gap-2 rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-600 transition-colors" title="Imprimir o guardar como PDF">
                <svg class="h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>Generar Informe Visual</span>
            </button>
        </div>

        <!-- Search panel -->
        <section id="search-panel" class="mb-6 hidden rounded-xl border border-slate-600/50 bg-slate-800/60 p-4 shadow-lg shadow-black/30">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-100">Buscar criptomonedas</h3>
                <button
                    id="close-search-btn"
                    type="button"
                    class="rounded-full bg-slate-700/70 px-2 py-1 text-xs text-slate-300 hover:bg-slate-600/80"
                >
                    Cerrar
                </button>
            </div>
            <div class="mb-3">
                <input
                    id="search-input"
                    type="text"
                    placeholder="Busca por nombre o símbolo (ej. BTC, Ethereum)…"
                    class="w-full rounded-lg border border-slate-600/70 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                />
            </div>
            <div id="search-results" class="max-h-56 space-y-1 overflow-y-auto text-sm text-slate-100">
                <p class="text-slate-400">Empieza a escribir para buscar criptomonedas…</p>
            </div>
        </section>

        <!-- Hero cards: carousel with all portfolio coins -->
        <section id="hero-cards" class="hero-carousel mb-8 flex gap-4 overflow-x-auto overflow-y-hidden pb-2 flex-nowrap scroll-smooth" aria-label="Destacados"></section>

        <!-- Main table: smaller text on mobile for readability -->
        <section class="print-report-page overflow-hidden rounded-xl border border-slate-600/50 bg-slate-800/40 shadow-lg shadow-black/20" aria-label="Tabla de cotizaciones">
            <div class="min-w-0 overflow-x-auto touch-pan-x">
                <table class="w-full min-w-[280px] sm:min-w-[400px] border-collapse text-xs sm:text-sm">
                    <thead>
                        <tr class="border-b border-slate-600/50 bg-slate-900/50">
                            <th class="sortable-th px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400" data-sort="moneda">Moneda <span class="sort-indicator"></span></th>
                            <th class="sortable-th px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400" data-sort="precio">Precio <span class="sort-indicator"></span></th>
                            <th class="sortable-th px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400" data-sort="cambio">Cambio 24h <span class="sort-indicator"></span></th>
                            <th class="sortable-th px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400" data-sort="market_cap">Market Cap <span class="sort-indicator"></span></th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400 w-20">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="table-body" class="divide-y divide-slate-600/30">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400">Cargando datos…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="px-4 py-2 text-xs text-slate-500 border-t border-slate-600/30">Valores expresados en USD (Dólares Americanos).</p>
        </section>

        <!-- Error message (hidden by default) -->
        <div id="error-message" class="mt-4 hidden rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300" role="alert"></div>

        <!-- Price history chart: solo desktop inline (hidden en móvil; en móvil SIEMPRE modal) -->
        <div id="chart-section" class="hidden max-md:hidden md:block w-full bg-slate-800/50 rounded-xl p-4 md:p-6 mb-8 border border-slate-700">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-100">Historial de precios</h2>
                    <p id="chart-subtitle" class="text-xs text-slate-400">Selecciona una moneda en la tabla para ver su historial.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button type="button" id="compare-portfolio-btn" class="no-print rounded-full bg-slate-700/80 px-3 py-1.5 text-slate-200 hover:bg-slate-600 transition-colors" title="Ver rendimiento % de todo el portafolio">Comparar Todo el Portafolio</button>
                    <button type="button" data-range="24h" class="chart-range-btn rounded-full bg-slate-800 px-3 py-1 text-slate-300 hover:bg-slate-700 transition-colors">24h</button>
                    <button type="button" data-range="7d" class="chart-range-btn chart-range-active rounded-full bg-emerald-600 px-3 py-1 text-white hover:bg-emerald-500 transition-colors">7d</button>
                    <button type="button" data-range="30d" class="chart-range-btn rounded-full bg-slate-800 px-3 py-1 text-slate-300 hover:bg-slate-700 transition-colors">30d</button>
                    <button type="button" data-range="1y" class="chart-range-btn rounded-full bg-slate-800 px-3 py-1 text-slate-300 hover:bg-slate-700 transition-colors">1y</button>
                </div>
            </div>
            <!-- Empty state: mensaje cuando no hay ninguna gráfica seleccionada -->
            <div id="chart-welcome-empty" class="flex flex-col items-center justify-center min-h-[300px] md:min-h-[400px] rounded-lg border-2 border-dashed border-slate-700 bg-slate-800/30 p-8 text-center">
                <svg class="w-12 h-12 text-slate-500 mb-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C6.75 20.496 6.246 21 5.625 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                <p class="text-slate-400 text-sm max-w-md">Seleccione una criptomoneda de la tabla para ver su historial detallado o compare el rendimiento de su portafolio completo.</p>
            </div>
            <div id="chart-area" class="relative w-full h-[300px] md:h-[500px] hidden">
                <div id="chart-empty-state" class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">No hay datos aún. Elige una moneda del portafolio.</div>
                <div id="chart-loading" class="chart-loading absolute inset-0 z-10 hidden flex items-center justify-center rounded-lg bg-slate-800/80 text-sm text-slate-300" aria-live="polite">Cargando…</div>
                <canvas id="priceChart"></canvas>
            </div>
        </div>

        <footer class="mt-10 border-t border-slate-700/50 bg-slate-900/80 px-4 py-4 text-center text-xs text-slate-400 sm:text-sm">
            <p>
                © 2026 Desarrollado por
                <a href="https://igniweb.com/" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-emerald-400 transition">IGNIWEB SAS</a>
                para el Grupo de Inversores CryptoInvestment.
            </p>
            <p class="mt-1.5">Última sincronización global: <span id="footer-last-sync" aria-live="polite">—</span></p>
        </footer>
    </div>

    <div id="dashboard-content" class="dashboard-export" aria-hidden="true" style="position:fixed;left:0;top:0;width:800px;max-width:100%;opacity:0;pointer-events:none;z-index:10001;overflow:auto;"></div>

    <!-- Chart modal (móvil): gráfica a pantalla completa; desktop usa #chart-section inline -->
    <div id="chart-modal" class="fixed inset-0 z-[100] hidden flex-col bg-slate-900 w-screen h-screen overflow-hidden" aria-modal="true" role="dialog" aria-labelledby="chart-modal-title">
        <div class="modal-body flex h-full flex-1 flex-col overflow-hidden p-2">
            <div class="modal-header relative flex h-[10%] min-h-[48px] shrink-0 items-center justify-between gap-3">
                <h2 id="chart-modal-title" class="text-base font-semibold text-slate-100">Historial de precios</h2>
                <button type="button" id="chart-modal-close" class="modal-close-btn flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-700/90 text-slate-200 hover:bg-slate-600 active:bg-slate-500 transition touch-manipulation" aria-label="Cerrar y volver a la tabla">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p id="chart-modal-subtitle" class="text-xs text-slate-400 mb-3">Selecciona una moneda en la tabla.</p>
            <div class="range-buttons mb-2 flex shrink-0 gap-2 text-xs flex-wrap">
                <button type="button" data-range="24h" class="chart-range-btn modal-range-btn rounded-full bg-slate-800 px-3 py-1.5 text-slate-300 hover:bg-slate-700 transition-colors">24h</button>
                <button type="button" data-range="7d" class="chart-range-btn modal-range-btn chart-range-active rounded-full bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-500 transition-colors">7d</button>
                <button type="button" data-range="30d" class="chart-range-btn modal-range-btn rounded-full bg-slate-800 px-3 py-1.5 text-slate-300 hover:bg-slate-700 transition-colors">30d</button>
                <button type="button" data-range="1y" class="chart-range-btn modal-range-btn rounded-full bg-slate-800 px-3 py-1.5 text-slate-300 hover:bg-slate-700 transition-colors">1y</button>
            </div>
            <div class="chart-container relative h-[70%] flex-1 min-h-0">
                <div id="chart-modal-empty" class="absolute inset-0 z-10 hidden flex items-center justify-center rounded-lg bg-slate-800/80 text-sm text-slate-400" aria-live="polite">Cargando…</div>
                <canvas id="priceChartModal" class="h-full w-full"></canvas>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const API_DATA_URL = '/api/crypto/data';
            const API_SEARCH_URL = '/api/crypto/search';
            const API_PORTFOLIO_URL = '/api/portfolio';
            const API_HISTORY_URL = '/api/crypto/history/';
            const API_HISTORY_BULK_URL = '/api/crypto/history-bulk';
            const REFRESH_INTERVAL_MS = 30000;
            const SEARCH_DEBOUNCE_MS = 400;
            var HERO_ACCENT_CLASSES = [
                    'rounded-xl border border-slate-600/50 bg-slate-800/40 p-5 shadow-lg shadow-black/20 transition hover:border-amber-500/30',
                    'rounded-xl border border-slate-600/50 bg-slate-800/40 p-5 shadow-lg shadow-black/20 transition hover:border-sky-500/30',
                    'rounded-xl border border-slate-600/50 bg-slate-800/40 p-5 shadow-lg shadow-black/20 transition hover:border-violet-500/30',
                    'rounded-xl border border-slate-600/50 bg-slate-800/40 p-5 shadow-lg shadow-black/20 transition hover:border-emerald-500/30'
                ];
                var HERO_TEXT_CLASSES = ['text-amber-400', 'text-sky-400', 'text-violet-400', 'text-emerald-400'];

            const lastUpdateEl = document.getElementById('last-update');
            const statusEl = document.getElementById('status');
            const tableBody = document.getElementById('table-body');
            const errorEl = document.getElementById('error-message');
            const heroCardsEl = document.getElementById('hero-cards');

            const addCryptoBtn = document.getElementById('add-crypto-btn');
            const searchPanel = document.getElementById('search-panel');
            const closeSearchBtn = document.getElementById('close-search-btn');
            const searchInput = document.getElementById('search-input');
            const searchResultsEl = document.getElementById('search-results');

            const chartCanvas = document.getElementById('priceChart');
            const chartSubtitle = document.getElementById('chart-subtitle');
            const chartEmptyState = document.getElementById('chart-empty-state');
            const chartSection = document.getElementById('chart-section');
            const chartWelcomeEmpty = document.getElementById('chart-welcome-empty');
            const chartArea = document.getElementById('chart-area');
            const comparePortfolioBtnTop = document.getElementById('compare-portfolio-btn-top');
            const chartModal = document.getElementById('chart-modal');
            const chartModalCanvas = document.getElementById('priceChartModal');
            const chartModalEmpty = document.getElementById('chart-modal-empty');
            const chartLoadingEl = document.getElementById('chart-loading');
            const chartModalClose = document.getElementById('chart-modal-close');
            const chartModalSubtitle = document.getElementById('chart-modal-subtitle');
            const rangeButtons = document.querySelectorAll('.chart-range-btn');
            const reportBtn = document.getElementById('report-btn');
            const comparePortfolioBtn = document.getElementById('compare-portfolio-btn');

            var COMPARE_COLORS = ['#f97316', '#3b82f6', '#a855f7', '#22c55e', '#eab308', '#ec4899', '#06b6d4', '#84cc16'];

            function isMobile() { return window.matchMedia('(max-width: 767px)').matches; }
            function openChartModal() {
                if (chartModal) {
                    chartModal.classList.remove('hidden');
                    chartModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                }
            }
            function showChartSectionWithWelcome() {
                if (isMobile()) return;
                if (chartSection) chartSection.classList.remove('hidden');
                if (chartWelcomeEmpty) chartWelcomeEmpty.classList.remove('hidden');
                if (chartArea) chartArea.classList.add('hidden');
                if (comparePortfolioBtnTop) comparePortfolioBtnTop.classList.remove('hidden');
            }
            function showChartArea() {
                if (chartWelcomeEmpty) chartWelcomeEmpty.classList.add('hidden');
                if (chartArea) chartArea.classList.remove('hidden');
            }
            function closeChartModal() {
                if (chartModal) {
                    chartModal.classList.add('hidden');
                    chartModal.classList.remove('flex');
                    document.body.style.overflow = 'auto';
                }
            }

            let searchTimer = null;
            let currentChart = null;
            let currentModalChart = null;
            let currentRange = '7d';
            let currentHistoryCmcId = null;
            let currentHistorySymbol = null;
            let compareMode = false;
            let tableData = [];
            let sortColumn = null;
            let sortDir = 'asc';
            function destroyChartInstances() {
                if (currentChart) { currentChart.destroy(); currentChart = null; }
                if (currentModalChart) { currentModalChart.destroy(); currentModalChart = null; }
            }

            function formatPrice(value) {
                if (value == null || value === '') return '—';
                const n = Number(value);
                if (isNaN(n)) return '—';
                if (n >= 1) return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 6 });
            }

            function formatPercent(value) {
                if (value == null || value === '') return '—';
                const n = Number(value);
                if (isNaN(n)) return '—';
                const sign = n >= 0 ? '+' : '';
                return sign + n.toFixed(2) + '%';
            }

            function formatMarketCap(value) {
                if (value == null || value === '') return '—';
                const n = Number(value);
                if (isNaN(n)) return '—';
                if (n >= 1e12) return '$' + (n / 1e12).toFixed(2) + 'T';
                if (n >= 1e9) return '$' + (n / 1e9).toFixed(2) + 'B';
                if (n >= 1e6) return '$' + (n / 1e6).toFixed(2) + 'M';
                return '$' + n.toLocaleString('en-US', { maximumFractionDigits: 0 });
            }

            function renderHeroCards(data) {
                var items = Array.isArray(data) ? data : [];
                if (items.length === 0) {
                    heroCardsEl.innerHTML = '';
                    return;
                }
                heroCardsEl.innerHTML = items.map(function (row, i) {
                    var cardClass = HERO_ACCENT_CLASSES[i % HERO_ACCENT_CLASSES.length];
                    var textClass = HERO_TEXT_CLASSES[i % HERO_TEXT_CLASSES.length];
                    return '<div class="flex-shrink-0 min-w-[200px] sm:min-w-[220px] ' + cardClass + '">' +
                        '<div class="flex items-center gap-2">' +
                        '<span class="text-xl sm:text-2xl font-bold ' + textClass + '">' + escapeHtml(row.symbol) + '</span>' +
                        '<span class="text-slate-400 text-sm truncate">' + escapeHtml(row.name) + '</span>' +
                        '</div>' +
                        '<p class="mt-2 text-lg sm:text-xl font-semibold">' + formatPrice(row.price_usd) + '</p>' +
                        '</div>';
                }).join('');
            }

            function renderTable(data) {
                if (!Array.isArray(data) || data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No hay monedas en el portafolio. Añade cryptos para ver cotizaciones.</td></tr>';
                    return;
                }
                var trashSvg = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
                tableBody.innerHTML = data.map(function (row) {
                    const change = row.percent_change_24h;
                    const isPositive = change != null && Number(change) >= 0;
                    const badgeClass = isPositive
                        ? 'rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-xs font-medium text-emerald-400'
                        : 'rounded-full bg-red-500/20 px-2.5 py-0.5 text-xs font-medium text-red-400';

                    return '<tr class="table-row cursor-pointer hover:bg-slate-700/20" data-cmc-id="' + String(row.cmc_id) + '" data-symbol="' + escapeHtml(row.symbol) + '" data-portfolio-id="' + String(row.portfolio_id) + '">' +
                        '<td class="px-4 py-3"><span class="font-medium">' + escapeHtml(row.symbol) + '</span> <span class="text-slate-400">' + escapeHtml(row.name) + '</span></td>' +
                        '<td class="px-4 py-3 text-right font-medium">' + formatPrice(row.price_usd) + '</td>' +
                        '<td class="px-4 py-3 text-right"><span class="' + badgeClass + '">' + formatPercent(change) + '</span></td>' +
                        '<td class="px-4 py-3 text-right text-slate-300">' + formatMarketCap(row.market_cap) + '</td>' +
                        '<td class="px-4 py-3 text-right"><button type="button" class="table-delete-btn inline-flex p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition" title="Quitar del portafolio" aria-label="Quitar del portafolio">' + trashSvg + '</button></td>' +
                        '</tr>';
                }).join('');
                if (currentHistoryCmcId && !compareMode) {
                    tableBody.querySelectorAll('tr.table-row').forEach(function (r) { r.classList.remove('bg-slate-700/50'); });
                    var sel = tableBody.querySelector('tr[data-cmc-id="' + String(currentHistoryCmcId) + '"]');
                    if (sel) sel.classList.add('bg-slate-700/50');
                }
            }

            function updateSortIndicators() {
                document.querySelectorAll('.sortable-th .sort-indicator').forEach(function (indicator) {
                    var th = indicator.closest('.sortable-th');
                    var key = th ? th.getAttribute('data-sort') : '';
                    if (sortColumn !== key) { indicator.textContent = ''; return; }
                    indicator.textContent = sortDir === 'asc' ? ' ↑' : ' ↓';
                });
            }

            function sortTableData(column) {
                if (sortColumn === column) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                else { sortColumn = column; sortDir = 'asc'; }
                var key = column;
                if (key === 'moneda') key = 'symbol';
                if (key === 'precio') key = 'price_usd';
                if (key === 'cambio') key = 'percent_change_24h';
                tableData.sort(function (a, b) {
                    var va = a[key], vb = b[key];
                    if (key === 'symbol' || key === 'name') {
                        va = (va || '').toString().toLowerCase();
                        vb = (vb || '').toString().toLowerCase();
                        return sortDir === 'asc' ? (va < vb ? -1 : va > vb ? 1 : 0) : (vb < va ? -1 : vb > va ? 1 : 0);
                    }
                    va = Number(va); vb = Number(vb);
                    if (isNaN(va)) va = 0; if (isNaN(vb)) vb = 0;
                    return sortDir === 'asc' ? va - vb : vb - va;
                });
                updateSortIndicators();
                renderTable(tableData);
                tableBody.querySelectorAll('tr.table-row').forEach(function (tr) { tr.classList.add('row-sorted'); });
                setTimeout(function () { tableBody.querySelectorAll('tr.table-row').forEach(function (tr) { tr.classList.remove('row-sorted'); }); }, 400);
            }

            function escapeHtml(s) {
                if (s == null) return '';
                const div = document.createElement('div');
                div.textContent = s;
                return div.innerHTML;
            }

            function setLastUpdate() {
                var now = new Date();
                var timeStr = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                var dateStr = now.toLocaleDateString(undefined, { day: '2-digit', month: '2-digit', year: 'numeric' });
                if (lastUpdateEl) lastUpdateEl.textContent = 'Última actualización: ' + timeStr;
                var footerSync = document.getElementById('footer-last-sync');
                if (footerSync) footerSync.textContent = dateStr + ' ' + timeStr;
            }

            function showError(msg) {
                errorEl.textContent = msg || 'Error al cargar datos.';
                errorEl.classList.remove('hidden');
            }

            function hideError() {
                errorEl.classList.add('hidden');
            }

            function toggleSearchPanel(show) {
                if (show) {
                    searchPanel.classList.remove('hidden');
                    searchInput.focus();
                } else {
                    searchPanel.classList.add('hidden');
                    searchInput.value = '';
                    searchResultsEl.innerHTML = '<p class="text-slate-400">Empieza a escribir para buscar criptomonedas…</p>';
                }
            }

            function renderSearchResults(items) {
                if (!Array.isArray(items) || items.length === 0) {
                    searchResultsEl.innerHTML = '<p class="text-slate-400">No se encontraron resultados.</p>';
                    return;
                }

                searchResultsEl.innerHTML = items.map(function (item) {
                    return (
                        '<button type="button" class="flex w-full items-center justify-between rounded-lg bg-slate-900/60 px-3 py-2 text-left text-slate-100 hover:bg-slate-800/80" ' +
                        'data-crypto-id="' + String(item.id) + '">' +
                        '<span><span class="font-semibold">' + escapeHtml(item.symbol) + '</span> ' +
                        '<span class="text-slate-400">' + escapeHtml(item.name) + '</span></span>' +
                        '</button>'
                    );
                }).join('');
            }

            function debounceSearch() {
                const query = searchInput.value.trim();
                if (searchTimer) {
                    clearTimeout(searchTimer);
                }
                if (query === '') {
                    searchResultsEl.innerHTML = '<p class="text-slate-400">Empieza a escribir para buscar criptomonedas…</p>';
                    return;
                }
                searchTimer = setTimeout(function () {
                    performSearch(query);
                }, SEARCH_DEBOUNCE_MS);
            }

            function performSearch(query) {
                searchResultsEl.innerHTML = '<p class="text-slate-400">Buscando…</p>';

                const url = API_SEARCH_URL + '?q=' + encodeURIComponent(query);
                fetch(url)
                    .then(function (res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(function (json) {
                        if (!json.success) {
                            throw new Error(json.message || 'Error en búsqueda');
                        }
                        renderSearchResults(json.data || []);
                    })
                    .catch(function (err) {
                        searchResultsEl.innerHTML =
                            '<p class="text-red-300">Error al buscar: ' + escapeHtml(err.message || 'desconocido') + '</p>';
                    });
            }

            function removeFromPortfolio(portfolioId) {
                var row = tableBody.querySelector('tr[data-portfolio-id="' + String(portfolioId) + '"]');
                if (row) {
                    row.classList.add('row-removing');
                    setTimeout(function () {
                        fetch(API_PORTFOLIO_URL + '/' + String(portfolioId), { method: 'DELETE', headers: { 'Accept': 'application/json' } })
                            .then(function (res) {
                                if (!res.ok) throw new Error('HTTP ' + res.status);
                                return res.json();
                            })
                            .then(function (json) {
                                if (json.success) fetchData();
                            })
                            .catch(function (err) {
                                row.classList.remove('row-removing');
                                showError(err.message || 'No se pudo quitar del portafolio.');
                            });
                    }, 250);
                }
            }

            function addToPortfolio(cryptocurrencyId) {
                fetch(API_PORTFOLIO_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ cryptocurrency_id: cryptocurrencyId }),
                })
                    .then(function (res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(function (json) {
                        if (!json.success) {
                            throw new Error(json.message || 'No se pudo agregar al portafolio');
                        }
                        toggleSearchPanel(false);
                        fetchData();
                    })
                    .catch(function (err) {
                        showError(err.message || 'No se pudo agregar la criptomoneda.');
                    });
            }

            function getRangeDates(range) {
                const to = new Date();
                const from = new Date(to.getTime());
                if (range === '1h') {
                    from.setHours(from.getHours() - 1);
                } else if (range === '7d') {
                    from.setDate(from.getDate() - 7);
                } else {
                    // default 24h
                    from.setDate(from.getDate() - 1);
                }
                return {
                    from: from.toISOString(),
                    to: to.toISOString(),
                };
            }

            function setRangeActive(range) {
                rangeButtons.forEach(function (btn) {
                    const r = btn.getAttribute('data-range');
                    if (r === range) {
                        btn.classList.remove('bg-slate-800', 'text-slate-300', 'bg-slate-700', 'text-slate-100');
                        btn.classList.add('bg-emerald-600', 'text-white', 'chart-range-active');
                    } else {
                        btn.classList.remove('bg-emerald-600', 'text-white', 'chart-range-active', 'bg-slate-700', 'text-slate-100');
                        btn.classList.add('bg-slate-800', 'text-slate-300');
                    }
                });
            }

            function getFormattedLabels(snapshots, range) {
                return snapshots.map(function (s) {
                    var d = new Date(s.recorded_at || 0);
                    if (range === '24h') return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
                    if (range === '1y') return d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
                    return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short' });
                });
            }

            function renderChart(symbol, snapshots, targetCanvas, chartOptions) {
                var canvas = targetCanvas || chartCanvas;
                if (window.innerWidth < 768 && chartModal && canvas === chartCanvas) {
                    openChartModal();
                    canvas = chartModalCanvas;
                }
                var emptyEl = (canvas === chartModalCanvas) ? chartModalEmpty : chartEmptyState;
                if (!canvas) return;

                var opts = chartOptions || {};
                var hasData = Array.isArray(snapshots) && snapshots.length > 0;
                destroyChartInstances();

                if (emptyEl) {
                    emptyEl.classList.toggle('hidden', hasData);
                    emptyEl.textContent = hasData ? '' : (canvas === chartModalCanvas ? 'No hay datos.' : 'No hay datos aún. Elige una moneda del portafolio.');
                }

                if (!hasData) return;

                var range = opts.range || currentRange;
                var labels = getFormattedLabels(snapshots, range);
                var prices = snapshots.map(function (s) { return Number(s.price_usd); });

                var ctx = canvas.getContext('2d');
                var chartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: symbol + ' USD',
                                data: prices,
                                borderColor: 'rgb(34,197,94)',
                                backgroundColor: 'rgba(34,197,94,0.15)',
                                borderWidth: 2,
                                tension: 0.1,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                pointHitRadius: 6,
                                spanGaps: true,
                            },
                        ],
                    },
                    plugins: [{
                        id: 'custom_canvas_background_color',
                        beforeDraw: function (chart) {
                            var ctx = chart.canvas.getContext('2d');
                            ctx.save();
                            ctx.globalCompositeOperation = 'destination-over';
                            ctx.fillStyle = '#1e293b';
                            ctx.fillRect(0, 0, chart.width, chart.height);
                            ctx.restore();
                        },
                    }],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return formatPrice(ctx.parsed.y);
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                ticks: {
                                    color: '#9ca3af',
                                    maxTicksLimit: 8,
                                    autoSkip: true,
                                    maxRotation: 0,
                                },
                                grid: { color: 'rgba(148,163,184,0.1)' },
                            },
                            y: {
                                ticks: {
                                    color: '#9ca3af',
                                    callback: function (value) {
                                        return '$' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
                                    },
                                },
                                grid: { color: 'rgba(148,163,184,0.08)' },
                            },
                        },
                    },
                });
                if (canvas === chartModalCanvas) currentModalChart = chartInstance; else currentChart = chartInstance;
            }

            function buildCommonTimeline(bulkData) {
                var times = [];
                Object.keys(bulkData).forEach(function (id) {
                    var snapshots = bulkData[id].snapshots || [];
                    snapshots.forEach(function (s) {
                        var t = new Date(s.recorded_at).getTime();
                        if (times.indexOf(t) === -1) times.push(t);
                    });
                });
                times.sort(function (a, b) { return a - b; });
                return times;
            }

            function getPriceAtTime(snapshots, time) {
                if (!snapshots.length) return null;
                var target = new Date(time).getTime();
                var best = snapshots[0];
                var bestDiff = Math.abs(new Date(best.recorded_at).getTime() - target);
                for (var i = 1; i < snapshots.length; i++) {
                    var d = Math.abs(new Date(snapshots[i].recorded_at).getTime() - target);
                    if (d < bestDiff) { bestDiff = d; best = snapshots[i]; }
                }
                return Number(best.price_usd);
            }

            function renderCompareChart(bulkData, range, targetCanvas) {
                var canvas = targetCanvas || chartCanvas;
                if (window.innerWidth < 768 && chartModal && canvas === chartCanvas) {
                    openChartModal();
                    canvas = chartModalCanvas;
                }
                var emptyEl = (canvas === chartModalCanvas) ? chartModalEmpty : chartEmptyState;
                if (!canvas) return;
                destroyChartInstances();
                var ids = Object.keys(bulkData);
                if (ids.length === 0) {
                    if (emptyEl) { emptyEl.classList.remove('hidden'); emptyEl.textContent = 'Añade al menos una moneda al portafolio para comparar.'; }
                    return;
                }
                if (emptyEl) { emptyEl.classList.add('hidden'); emptyEl.textContent = ''; }
                var timeline = buildCommonTimeline(bulkData);
                if (timeline.length === 0) {
                    if (emptyEl) { emptyEl.classList.remove('hidden'); emptyEl.textContent = 'No hay datos de historial para el rango seleccionado.'; }
                    return;
                }
                var labels = timeline.map(function (t) {
                    var d = new Date(t);
                    if (range === '24h') return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
                    if (range === '1y') return d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
                    return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short' });
                });
                var datasets = [];
                ids.forEach(function (id, idx) {
                    var meta = bulkData[id];
                    var snapshots = meta.snapshots || [];
                    if (snapshots.length === 0) return;
                    var firstPrice = Number(snapshots[0].price_usd);
                    if (!firstPrice) return;
                    var values = timeline.map(function (t) {
                        var price = getPriceAtTime(snapshots, t);
                        if (price == null) return null;
                        return ((price / firstPrice) - 1) * 100;
                    });
                    var color = COMPARE_COLORS[idx % COMPARE_COLORS.length];
                    var rgba = color.startsWith('rgb') ? color.replace(')', ', 0.15)').replace('rgb(', 'rgba(') : (color.length === 7 ? color + '26' : color);
                    datasets.push({
                        label: meta.symbol,
                        data: values,
                        borderColor: color,
                        backgroundColor: rgba,
                        borderWidth: 2,
                        tension: 0.1,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHitRadius: 6,
                        spanGaps: true,
                    });
                });
                if (datasets.length === 0) {
                    if (emptyEl) { emptyEl.classList.remove('hidden'); emptyEl.textContent = 'No hay datos para mostrar.'; }
                    return;
                }
                var isMobile = typeof window !== 'undefined' && window.innerWidth < 768;
                var legendPosition = isMobile ? 'bottom' : 'top';
                var showLegend = datasets.length <= 6;
                var ctx = canvas.getContext('2d');
                var chartInstance = new Chart(ctx, {
                    type: 'line',
                    data: { labels: labels, datasets: datasets },
                    plugins: [{
                        id: 'custom_canvas_background_color',
                        beforeDraw: function (chart) {
                            var ctx = chart.canvas.getContext('2d');
                            ctx.save();
                            ctx.globalCompositeOperation = 'destination-over';
                            ctx.fillStyle = '#1e293b';
                            ctx.fillRect(0, 0, chart.width, chart.height);
                            ctx.restore();
                        },
                    }],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: showLegend, position: legendPosition, labels: { color: '#9ca3af', boxWidth: 12 } },
                            tooltip: { callbacks: { label: function (ctx) { var v = ctx.parsed.y; if (v == null) return ctx.dataset.label + ': —'; return ctx.dataset.label + ': ' + (v >= 0 ? '+' : '') + v.toFixed(2) + '%'; } } },
                        },
                        scales: {
                            x: { ticks: { color: '#9ca3af', maxTicksLimit: 8, autoSkip: true, maxRotation: 0 }, grid: { color: 'rgba(148,163,184,0.1)' } },
                            y: { ticks: { color: '#9ca3af', callback: function (v) { return (v == null ? '' : (v >= 0 ? '+' : '') + Number(v).toFixed(1) + '%'); } }, grid: { color: 'rgba(148,163,184,0.08)' } },
                        },
                    },
                });
                if (canvas === chartModalCanvas) currentModalChart = chartInstance; else currentChart = chartInstance;
            }

            function loadCompareView() {
                var ids = (tableData || []).map(function (r) { return r.cmc_id; }).filter(Boolean);
                if (ids.length === 0) {
                    if (chartEmptyState) { chartEmptyState.classList.remove('hidden'); chartEmptyState.textContent = 'Añade al menos una moneda al portafolio para comparar.'; }
                    return;
                }
                var useModal = isMobile() && chartModal;
                if (useModal) {
                    openChartModal();
                    if (chartModalSubtitle) chartModalSubtitle.textContent = 'Rendimiento consolidado (% cambio desde inicio del periodo).';
                    window.dispatchEvent(new Event('resize'));
                } else {
                    if (chartSection) chartSection.classList.remove('hidden');
                    showChartArea();
                    window.dispatchEvent(new Event('resize'));
                }
                setRangeActive(currentRange);
                if (chartSubtitle) chartSubtitle.textContent = 'Rendimiento consolidado (% cambio desde inicio del periodo).';
                showChartLoading(useModal);
                var url = API_HISTORY_BULK_URL + '?ids=' + encodeURIComponent(ids.join(',')) + '&range=' + encodeURIComponent(currentRange);
                fetch(url).then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
                    .then(function (json) {
                        if (!json.success) throw new Error(json.message || 'Error');
                        hideChartLoading(useModal);
                        renderCompareChart(json.data || {}, currentRange, useModal ? chartModalCanvas : chartCanvas);
                        if (useModal) window.dispatchEvent(new Event('resize'));
                    })
                    .catch(function (err) {
                        hideChartLoading(useModal);
                        if (useModal && chartModalEmpty) {
                            chartModalEmpty.classList.remove('hidden');
                            chartModalEmpty.textContent = 'Error al cargar comparación.';
                        } else if (chartEmptyState) {
                            chartEmptyState.classList.remove('hidden');
                            chartEmptyState.textContent = 'Error al cargar comparación.';
                        }
                        showError(err.message || 'No se pudo cargar la comparación.');
                    });
            }

            function exitCompareMode() {
                compareMode = false;
                if (comparePortfolioBtn) comparePortfolioBtn.textContent = 'Comparar Todo el Portafolio';
                if (comparePortfolioBtnTop) comparePortfolioBtnTop.innerHTML = '<svg class="h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Comparar Portafolio</span>';
                if (chartSubtitle) chartSubtitle.textContent = 'Selecciona una moneda en la tabla para ver su historial.';
                if (currentChart) { currentChart.destroy(); currentChart = null; }
                if (currentModalChart) { currentModalChart.destroy(); currentModalChart = null; }
                closeChartModal();
                if (chartEmptyState) { chartEmptyState.classList.remove('hidden'); chartEmptyState.textContent = 'No hay datos aún. Elige una moneda del portafolio.'; }
                if (currentHistoryCmcId && currentHistorySymbol) loadHistory(currentHistoryCmcId, currentHistorySymbol);
            }

            function showChartLoading(useModal) {
                if (useModal && chartModalEmpty) {
                    chartModalEmpty.classList.remove('hidden');
                    chartModalEmpty.textContent = 'Cargando…';
                }
                if (!useModal && chartLoadingEl) chartLoadingEl.classList.remove('hidden');
            }

            function hideChartLoading(useModal) {
                if (chartModalEmpty) chartModalEmpty.classList.add('hidden');
                if (chartLoadingEl) chartLoadingEl.classList.add('hidden');
            }

            function loadHistory(cmcId, symbol) {
                compareMode = false;
                if (comparePortfolioBtn) comparePortfolioBtn.textContent = 'Comparar Todo el Portafolio';
                currentHistoryCmcId = cmcId;
                currentHistorySymbol = symbol;
                setRangeActive(currentRange);

                var useModal = isMobile() && chartModal;
                if (useModal) {
                    openChartModal();
                } else {
                    if (chartSection) chartSection.classList.remove('hidden');
                    showChartArea();
                    window.dispatchEvent(new Event('resize'));
                }
                var subtitleText = 'Historial de ' + symbol + ' (' + currentRange + ').';
                if (chartSubtitle) chartSubtitle.textContent = subtitleText;
                if (chartModalSubtitle) chartModalSubtitle.textContent = subtitleText;
                showChartLoading(useModal);

                if (chartModalCanvas && currentModalChart) {
                    currentModalChart.destroy();
                    currentModalChart = null;
                }
                if (chartCanvas && currentChart) {
                    currentChart.destroy();
                    currentChart = null;
                }

                var url = API_HISTORY_URL + encodeURIComponent(String(cmcId)) + '?range=' + encodeURIComponent(currentRange);

                fetch(url)
                    .then(function (res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(function (json) {
                        if (!json.success) throw new Error(json.message || 'Error al cargar historial');
                        hideChartLoading(useModal);
                        var payload = json.data || {};
                        var snapshots = (payload.snapshots || []).slice();
                        var labels = (payload.labels || []).slice();
                        var targetCanvas = useModal ? chartModalCanvas : chartCanvas;
                        renderChart(symbol, snapshots, targetCanvas, { labels: labels, range: currentRange });
                    })
                    .catch(function (err) {
                        hideChartLoading(useModal);
                        if (chartModalEmpty) {
                            chartModalEmpty.classList.remove('hidden');
                            chartModalEmpty.textContent = 'Error al cargar.';
                        }
                        showError(err.message || 'No se pudo cargar el historial.');
                    });
            }

            function fetchData() {
                statusEl.textContent = 'Actualizando…';
                hideError();

                fetch(API_DATA_URL)
                    .then(function (res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(function (json) {
                        if (!json.success) {
                            throw new Error(json.message || 'Error en la respuesta');
                        }
                        const data = json.data || [];
                        tableData = data.slice();
                        renderHeroCards(data);
                        if (sortColumn) {
                            var key = sortColumn;
                            if (key === 'moneda') key = 'symbol';
                            if (key === 'precio') key = 'price_usd';
                            if (key === 'cambio') key = 'percent_change_24h';
                            tableData.sort(function (a, b) {
                                var va = a[key], vb = b[key];
                                if (key === 'symbol' || key === 'name') {
                                    va = (va || '').toString().toLowerCase();
                                    vb = (vb || '').toString().toLowerCase();
                                    return sortDir === 'asc' ? (va < vb ? -1 : va > vb ? 1 : 0) : (vb < va ? -1 : vb > va ? 1 : 0);
                                }
                                va = Number(va); vb = Number(vb);
                                if (isNaN(va)) va = 0; if (isNaN(vb)) vb = 0;
                                return sortDir === 'asc' ? va - vb : vb - va;
                            });
                        }
                        renderTable(tableData);
                        setLastUpdate();
                        statusEl.textContent = 'Actualizado';
                        var isModalOpen = !!(chartModal && !chartModal.classList.contains('hidden'));
                        if (tableData.length > 0) {
                            if (comparePortfolioBtnTop) comparePortfolioBtnTop.classList.remove('hidden');
                            if (!isModalOpen && isMobile()) {
                                if (chartSection) chartSection.classList.add('hidden');
                            } else if (!isModalOpen) {
                                showChartSectionWithWelcome();
                            }
                        } else {
                            if (!isModalOpen && chartSection) chartSection.classList.add('hidden');
                            if (comparePortfolioBtnTop) comparePortfolioBtnTop.classList.add('hidden');
                        }
                    })
                    .catch(function (err) {
                        showError(err.message || 'No se pudo conectar con el servidor.');
                        statusEl.textContent = 'Error';
                    });
            }

            // Event wiring
            addCryptoBtn.addEventListener('click', function () {
                toggleSearchPanel(true);
            });

            closeSearchBtn.addEventListener('click', function () {
                toggleSearchPanel(false);
            });

            searchInput.addEventListener('input', debounceSearch);

            searchResultsEl.addEventListener('click', function (event) {
                const btn = event.target.closest('[data-crypto-id]');
                if (!btn) return;
                const id = btn.getAttribute('data-crypto-id');
                if (!id) return;
                addToPortfolio(Number(id));
            });

            tableBody.addEventListener('click', function (event) {
                var deleteBtn = event.target.closest('.table-delete-btn');
                if (deleteBtn) {
                    event.preventDefault();
                    event.stopPropagation();
                    var deleteRow = deleteBtn.closest('tr[data-portfolio-id]');
                    if (deleteRow) removeFromPortfolio(deleteRow.getAttribute('data-portfolio-id'));
                    return;
                }
                const row = event.target.closest('tr[data-cmc-id]');
                if (!row) return;
                tableBody.querySelectorAll('tr.table-row').forEach(function (r) { r.classList.remove('bg-slate-700/50'); });
                row.classList.add('bg-slate-700/50');
                const cmcId = row.getAttribute('data-cmc-id');
                const symbol = row.getAttribute('data-symbol') || '';
                if (!cmcId) return;
                loadHistory(cmcId, symbol);
            });

            var tableEl = tableBody.closest('table');
            if (tableEl) {
                tableEl.querySelector('thead').addEventListener('click', function (event) {
                    var th = event.target.closest('.sortable-th');
                    if (!th) return;
                    sortTableData(th.getAttribute('data-sort'));
                });
            }

            rangeButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var range = btn.getAttribute('data-range') || '24h';
                    currentRange = range;
                    setRangeActive(range);
                    if (compareMode) loadCompareView();
                    else if (currentHistoryCmcId) loadHistory(currentHistoryCmcId, currentHistorySymbol || '');
                });
            });

            function doComparePortfolio() {
                if (compareMode) exitCompareMode();
                else {
                    compareMode = true;
                    if (comparePortfolioBtn) comparePortfolioBtn.textContent = 'Ver una moneda';
                    if (comparePortfolioBtnTop) comparePortfolioBtnTop.textContent = 'Ver una moneda';
                    tableBody.querySelectorAll('tr.table-row').forEach(function (r) { r.classList.remove('bg-slate-700/50'); });
                    loadCompareView();
                }
            }
            if (comparePortfolioBtn) comparePortfolioBtn.addEventListener('click', doComparePortfolio);
            if (comparePortfolioBtnTop) comparePortfolioBtnTop.addEventListener('click', doComparePortfolio);

            function escapeHtml(s) {
                if (s == null) return '';
                var t = String(s);
                return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function buildDashboardContent() {
                var syncEl = document.getElementById('footer-last-sync');
                var syncText = (syncEl && syncEl.textContent) ? escapeHtml(syncEl.textContent.trim()) : '—';
                var data = tableData || [];
                var rows = data.map(function (r) {
                    var pct = r.percent_change_24h;
                    var pctNum = Number(pct);
                    var cambioClass = !isNaN(pctNum) && pctNum < 0 ? 'negative' : 'positive';
                    var cambioStr = formatPercent(pct);
                    var moneda = escapeHtml(r.symbol || '') + ' · ' + escapeHtml(r.name || '');
                    return '<tr><td>' + moneda + '</td><td class="col-precio">' + escapeHtml(formatPrice(r.price_usd)) + '</td><td class="col-cambio ' + cambioClass + '">' + escapeHtml(cambioStr) + '</td><td class="col-cap">' + escapeHtml(formatMarketCap(r.market_cap)) + '</td></tr>';
                }).join('');
                var tableHtml = '<table class="report-export-table"><thead><tr><th>Moneda</th><th class="col-precio">Precio</th><th class="col-cambio">Cambio 24h</th><th class="col-cap">Market Cap</th></tr></thead><tbody>' + (rows || '<tr><td colspan="4" style="text-align:center;color:#94a3b8;">Sin datos</td></tr>') + '</tbody></table>';
                var chartImg = '';
                var chartToUse = currentChart || currentModalChart;
                if (chartToUse && typeof chartToUse.toBase64Image === 'function') {
                    try {
                        chartImg = '<div class="report-chart-block"><h3>Rendimiento consolidado</h3><img src="' + chartToUse.toBase64Image('image/png') + '" alt="Gráfica de rendimiento" /></div>';
                    } catch (e) { chartImg = '<div class="report-chart-block"><h3>Rendimiento consolidado</h3><p style="color:#94a3b8;font-size:0.75rem;">Gráfica no disponible</p></div>'; }
                } else {
                    chartImg = '<div class="report-chart-block"><h3>Rendimiento consolidado</h3><p style="color:#94a3b8;font-size:0.75rem;">Seleccione &quot;Comparar Todo el Portafolio&quot; y vuelva a generar el informe.</p></div>';
                }
                var headerHtml = '<div class="report-export-header"><h1>Sistema de Gestión de Activos Crypto - CryptoInvestment</h1><p class="report-sync">Última sincronización global: ' + syncText + '</p></div>';
                return '<div style="background-color:#0f172a;color:#f1f5f9;padding:40px;min-height:100vh;">' + headerHtml + tableHtml + chartImg + '</div>';
            }

            function generateReportVisual() {
                function doPrint() {
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () { window.print(); });
                    });
                }
                function restoreBtn() {
                    reportBtn.disabled = false;
                    reportBtn.innerHTML = '<svg class="h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Generar Informe Visual</span>';
                }
                if (typeof html2pdf !== 'undefined') {
                    var dashboardEl = document.getElementById('dashboard-content');
                    if (dashboardEl) {
                        dashboardEl.innerHTML = buildDashboardContent();
                        dashboardEl.style.position = 'fixed';
                        dashboardEl.style.left = '0';
                        dashboardEl.style.top = '0';
                        dashboardEl.style.zIndex = '10000';
                        dashboardEl.style.width = '800px';
                        dashboardEl.style.maxWidth = '100%';
                        dashboardEl.style.overflow = 'auto';
                        dashboardEl.style.opacity = '1';
                        dashboardEl.style.pointerEvents = 'auto';
                        dashboardEl.style.backgroundColor = '#0f172a';
                        dashboardEl.style.color = '#f1f5f9';
                        reportBtn.disabled = true;
                        reportBtn.textContent = 'Generando…';
                        var opt = {
                            margin: 12,
                            filename: 'informe-cryptoinvestment.pdf',
                            image: { type: 'png', quality: 1 },
                            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#0f172a', logging: true },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
                        };
                        html2pdf().set(opt).from(dashboardEl).save().then(function () {
                            dashboardEl.innerHTML = '';
                            dashboardEl.style.opacity = '0';
                            dashboardEl.style.pointerEvents = 'none';
                            restoreBtn();
                        }).catch(function () {
                            dashboardEl.innerHTML = '';
                            dashboardEl.style.opacity = '0';
                            dashboardEl.style.pointerEvents = 'none';
                            doPrint();
                            restoreBtn();
                        });
                        return;
                    }
                }
                doPrint();
            }

            if (reportBtn) {
                reportBtn.addEventListener('click', function () {
                    generateReportVisual();
                });
            }

            if (chartModalClose) {
                chartModalClose.addEventListener('click', closeChartModal);
            }
            if (chartModal) {
                chartModal.addEventListener('click', function (e) {
                    if (e.target === chartModal) closeChartModal();
                });
            }

            fetchData();
            setInterval(fetchData, REFRESH_INTERVAL_MS);
        })();
    </script>
</body>
</html>
