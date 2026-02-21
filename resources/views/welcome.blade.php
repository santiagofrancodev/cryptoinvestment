<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema de Gestión de Activos Crypto - CryptoInvestment</title>

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: { dark: { bg: '#0f172a', card: '#1e293b' } }
                }
            }
        }
    </script>

    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js for price history line chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { background-color: #0f172a; font-family: 'Inter', sans-serif; }
        #table-body tr { transition: opacity 0.25s ease, background-color 0.2s ease; }
        #table-body tr.row-removing { opacity: 0; transform: scale(0.98); transition: opacity 0.25s ease, transform 0.25s ease; }
        #table-body tr.row-sorted { animation: rowHighlight 0.4s ease; }
        @keyframes rowHighlight { from { background-color: rgba(34, 197, 94, 0.08); } to { background-color: transparent; } }
        .sortable-th { cursor: pointer; user-select: none; }
        .sortable-th:hover { color: rgb(203, 213, 225); }
        /* Hero carousel: subtle scrollbar */
        #hero-cards::-webkit-scrollbar { height: 6px; }
        #hero-cards::-webkit-scrollbar-track { background: rgba(30, 41, 59, 0.5); border-radius: 3px; }
        #hero-cards::-webkit-scrollbar-thumb { background: rgba(100, 116, 139, 0.6); border-radius: 3px; }
        #hero-cards::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.7); }
        @supports (scrollbar-width: thin) { #hero-cards { scrollbar-width: thin; scrollbar-color: rgba(100, 116, 139, 0.6) rgba(30, 41, 59, 0.5); } }
        /* Chart modal (mobile) */
        #chart-modal.backdrop-blur { backdrop-filter: blur(4px); }
    </style>
</head>
<body class="min-h-screen text-slate-100 antialiased">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
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
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-medium text-emerald-950 shadow shadow-emerald-500/40 hover:bg-emerald-400"
            >
                <span class="text-base leading-none">+</span>
                <span>Agregar cripto</span>
            </button>
        </div>

        <!-- Last update + status -->
        <div class="mb-4 flex flex-wrap items-center gap-4 text-sm">
            <span id="last-update" class="text-slate-400">Última actualización: —</span>
            <span id="status" class="rounded-full bg-slate-700/50 px-3 py-1 text-slate-300" aria-live="polite">Cargando…</span>
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
        <section class="overflow-hidden rounded-xl border border-slate-600/50 bg-slate-800/40 shadow-lg shadow-black/20" aria-label="Tabla de cotizaciones">
            <div class="overflow-x-auto">
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

        <!-- Price history chart (desktop: visible below table) -->
        <section id="chart-section" class="mt-8 hidden rounded-xl border border-slate-600/50 bg-slate-800/40 p-4 shadow-lg shadow-black/20 md:block">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-100">Historial de precios</h2>
                    <p id="chart-subtitle" class="text-xs text-slate-400">Selecciona una moneda en la tabla para ver su historial.</p>
                </div>
                <div class="flex gap-2 text-xs">
                    <button type="button" data-range="1h" class="chart-range-btn rounded-full bg-slate-800 px-3 py-1 text-slate-300 hover:bg-slate-700">1h</button>
                    <button type="button" data-range="24h" class="chart-range-btn rounded-full bg-slate-700 px-3 py-1 text-slate-100">24h</button>
                    <button type="button" data-range="7d" class="chart-range-btn rounded-full bg-slate-800 px-3 py-1 text-slate-300 hover:bg-slate-700">7d</button>
                </div>
            </div>
            <div class="relative">
                <div id="chart-empty-state" class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">No hay datos aún. Elige una moneda del portafolio.</div>
                <canvas id="priceChart" class="h-64 w-full"></canvas>
            </div>
        </section>

        <footer class="mt-10 border-t border-slate-700/50 bg-slate-900/80 px-4 py-4 text-center text-xs text-slate-400 sm:text-sm">
            <p>
                © 2026 Desarrollado por
                <a href="https://igniweb.com/" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-emerald-400 transition">IGNIWEB SAS</a>
                para el Grupo de Inversores CryptoInvestment.
            </p>
            <p class="mt-1.5">Última sincronización global: <span id="footer-last-sync" aria-live="polite">—</span></p>
        </footer>
    </div>

    <!-- Chart modal / slide-over (mobile): full-screen chart with Cerrar -->
    <div id="chart-modal" class="chart-modal fixed inset-0 z-50 hidden flex-col bg-slate-900/95 backdrop-blur border-slate-600/50 md:hidden" aria-modal="true" role="dialog" aria-labelledby="chart-modal-title">
        <div class="flex flex-1 flex-col overflow-hidden p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="chart-modal-title" class="text-base font-semibold text-slate-100">Historial de precios</h2>
                <button type="button" id="chart-modal-close" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:bg-slate-600 transition" aria-label="Cerrar">Cerrar</button>
            </div>
            <p id="chart-modal-subtitle" class="text-xs text-slate-400 mb-3">Selecciona una moneda en la tabla.</p>
            <div class="flex gap-2 text-xs mb-3">
                <button type="button" data-range="1h" class="chart-range-btn modal-range-btn rounded-full bg-slate-800 px-3 py-1.5 text-slate-300 hover:bg-slate-700">1h</button>
                <button type="button" data-range="24h" class="chart-range-btn modal-range-btn rounded-full bg-slate-700 px-3 py-1.5 text-slate-100">24h</button>
                <button type="button" data-range="7d" class="chart-range-btn modal-range-btn rounded-full bg-slate-800 px-3 py-1.5 text-slate-300 hover:bg-slate-700">7d</button>
            </div>
            <div class="relative flex-1 min-h-[240px]">
                <div id="chart-modal-empty" class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Cargando…</div>
                <canvas id="priceChartModal" class="h-full w-full min-h-[240px]"></canvas>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const API_DATA_URL = '/api/crypto/data';
            const API_SEARCH_URL = '/api/crypto/search';
            const API_PORTFOLIO_URL = '/api/portfolio';
            const API_HISTORY_URL = '/api/crypto/history/';
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
            const chartModal = document.getElementById('chart-modal');
            const chartModalCanvas = document.getElementById('priceChartModal');
            const chartModalEmpty = document.getElementById('chart-modal-empty');
            const chartModalClose = document.getElementById('chart-modal-close');
            const chartModalSubtitle = document.getElementById('chart-modal-subtitle');
            const rangeButtons = document.querySelectorAll('.chart-range-btn');

            function isMobile() { return window.matchMedia('(max-width: 767px)').matches; }
            function openChartModal() {
                if (chartModal) {
                    chartModal.classList.remove('hidden');
                    chartModal.classList.add('flex');
                }
            }
            function closeChartModal() {
                if (chartModal) {
                    chartModal.classList.add('hidden');
                    chartModal.classList.remove('flex');
                }
            }

            let searchTimer = null;
            let currentChart = null;
            let currentModalChart = null;
            let currentRange = '24h';
            let currentHistoryCmcId = null;
            let currentHistorySymbol = null;
            let tableData = [];
            let sortColumn = null;
            let sortDir = 'asc';

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
                        btn.classList.remove('bg-slate-800', 'text-slate-300');
                        btn.classList.add('bg-slate-700', 'text-slate-100');
                    } else {
                        btn.classList.add('bg-slate-800', 'text-slate-300');
                        btn.classList.remove('bg-slate-700', 'text-slate-100');
                    }
                });
            }

            function renderChart(symbol, snapshots, targetCanvas) {
                var canvas = targetCanvas || chartCanvas;
                var emptyEl = (canvas === chartModalCanvas) ? chartModalEmpty : chartEmptyState;
                if (!canvas) return;

                var hasData = Array.isArray(snapshots) && snapshots.length > 0;
                if (emptyEl) {
                    emptyEl.classList.toggle('hidden', hasData);
                    emptyEl.textContent = hasData ? '' : (canvas === chartModalCanvas ? 'No hay datos.' : 'No hay datos aún. Elige una moneda del portafolio.');
                }

                if (!hasData) {
                    if (canvas === chartModalCanvas && currentModalChart) {
                        currentModalChart.destroy();
                        currentModalChart = null;
                    } else if (currentChart) {
                        currentChart.destroy();
                        currentChart = null;
                    }
                    return;
                }

                var labels = snapshots.map(function (s) {
                    var d = new Date(s.recorded_at);
                    return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
                });
                var prices = snapshots.map(function (s) { return Number(s.price_usd); });

                if (canvas === chartModalCanvas && currentModalChart) {
                    currentModalChart.destroy();
                    currentModalChart = null;
                } else if (canvas === chartCanvas && currentChart) {
                    currentChart.destroy();
                    currentChart = null;
                }

                var ctx = canvas.getContext('2d');
                var chartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: symbol + ' USD',
                                data: prices,
                                borderColor: 'rgb(34,197,94)', // emerald-500
                                backgroundColor: 'rgba(34,197,94,0.15)',
                                borderWidth: 2,
                                tension: 0.25,
                                pointRadius: 0,
                                pointHitRadius: 6,
                            },
                        ],
                    },
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
                                ticks: { color: '#9ca3af' },
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

            function loadHistory(cmcId, symbol) {
                currentHistoryCmcId = cmcId;
                currentHistorySymbol = symbol;
                setRangeActive(currentRange);

                var useModal = isMobile() && chartModal;
                var subtitleText = 'Historial de ' + symbol + ' (' + currentRange + ').';
                if (chartSubtitle) chartSubtitle.textContent = subtitleText;
                if (chartModalSubtitle) chartModalSubtitle.textContent = subtitleText;

                if (useModal) {
                    openChartModal();
                    if (chartModalEmpty) { chartModalEmpty.classList.remove('hidden'); chartModalEmpty.textContent = 'Cargando…'; }
                }

                var range = getRangeDates(currentRange);
                var url = API_HISTORY_URL + encodeURIComponent(String(cmcId)) + '?from=' + encodeURIComponent(range.from) + '&to=' + encodeURIComponent(range.to);

                fetch(url)
                    .then(function (res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(function (json) {
                        if (!json.success) throw new Error(json.message || 'Error al cargar historial');
                        var payload = json.data || {};
                        var snapshots = payload.snapshots || [];
                        var targetCanvas = useModal ? chartModalCanvas : chartCanvas;
                        renderChart(symbol, snapshots, targetCanvas);
                    })
                    .catch(function (err) {
                        if (chartModalEmpty) { chartModalEmpty.classList.remove('hidden'); chartModalEmpty.textContent = 'Error al cargar.'; }
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
                    if (currentHistoryCmcId) loadHistory(currentHistoryCmcId, currentHistorySymbol || '');
                });
            });

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
