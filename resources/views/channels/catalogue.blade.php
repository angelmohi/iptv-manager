<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catálogo — {{ config('app.name') }}</title>

    <link rel="shortcut icon" href="{{ asset('assets/img/logo.png') }}" type="image/x-icon">
    <link href="https://fonts.bunny.net/css?family=nunito" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome-free-5.15.4/css/solid.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome-free-5.15.4/css/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/coreui/coreui.css') }}">

    <style>
        :root {
            --bg:        #0d0e14;
            --bg-soft:   #161823;
            --bg-card:   #1c1f2e;
            --border:    #2a2d3e;
            --text:      #e7e9ee;
            --text-dim:  #9aa0b4;
            --accent:    #ffc107;
            --accent-2:  #c3b5ff;
        }
        html { overflow-y: scroll; }
        html, body { background: var(--bg); }
        body {
            font-family: 'Nunito', sans-serif;
            color: var(--text);
            min-height: 100vh;
        }
        body.modal-open { padding-right: 0 !important; }

        /* ── Header ───────────────────────────────────────────── */
        .catalogue-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 1.4rem 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,.4);
        }
        .catalogue-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: 1px;
            background: linear-gradient(135deg, #a8c0ff 0%, #c3b5ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        .catalogue-subtitle { color: rgba(255,255,255,.55); font-size: .85rem; margin: 0;}
        .stat-badge {
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.85);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 999px;
            padding: .3rem .85rem;
            font-size: .8rem;
            font-weight: 600;
        }

        /* ── Tabs ─────────────────────────────────────────────── */
        .tab-bar {
            display: flex; flex-wrap: wrap; gap: .5rem;
            padding: 1rem 1.5rem 0;
        }
        .tab-btn {
            background: var(--bg-soft);
            color: var(--text-dim);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: .45rem 1.1rem;
            font-weight: 600;
            font-size: .9rem;
            cursor: pointer;
            transition: all .15s ease;
        }
        .tab-btn:hover { color: var(--text); border-color: #3b3f55;}
        .tab-btn.active {
            background: linear-gradient(135deg, #321fdb 0%, #5b3df5 100%);
            color: #fff;
            border-color: transparent;
        }

        /* ── Toolbar ──────────────────────────────────────────── */
        .toolbar {
            display: flex; flex-wrap: wrap; gap: .6rem;
            padding: 1rem 1.5rem;
            align-items: center;
        }
        .toolbar .search {
            flex: 1 1 240px;
            min-width: 200px;
            position: relative;
        }
        .toolbar .search input {
            width: 100%;
            background: var(--bg-soft);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 999px;
            padding: .55rem 1rem .55rem 2.4rem;
            font-size: .9rem;
        }
        .toolbar .search input:focus {
            outline: none;
            border-color: #5b3df5;
            box-shadow: 0 0 0 .2rem rgba(91,61,245,.2);
        }
        .toolbar .search i {
            position: absolute;
            left: .9rem; top: 50%; transform: translateY(-50%);
            color: var(--text-dim);
        }
        .toolbar select {
            background: var(--bg-soft);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 999px;
            padding: .5rem .9rem;
            font-size: .85rem;
            min-width: 140px;
        }
        .toolbar select:focus { outline: none; border-color: #5b3df5; }

        /* ── Grid ─────────────────────────────────────────────── */
        .grid-wrap { padding: .25rem 1.5rem 2rem; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1.2rem;
        }
        @media (min-width: 768px)  { .grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); } }
        @media (min-width: 1200px) { .grid { grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); } }

        .card-item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            display: flex; flex-direction: column;
        }
        .card-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0,0,0,.5);
            border-color: #3b3f55;
        }
        .card-poster {
            position: relative;
            aspect-ratio: 2 / 3;
            background: linear-gradient(135deg, #2a2d3e, #1a1c2a);
            overflow: hidden;
        }
        .card-poster img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .card-poster .no-poster {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            text-align: center; padding: .8rem;
            color: var(--text-dim); font-weight: 700; font-size: .9rem;
        }
        .card-poster .rating-pill {
            position: absolute; top: .5rem; right: .5rem;
            background: rgba(0,0,0,.75);
            color: var(--accent);
            font-weight: 700; font-size: .75rem;
            padding: .2rem .5rem;
            border-radius: 999px;
            backdrop-filter: blur(4px);
        }
        .card-poster .platform-pill {
            position: absolute; bottom: .5rem; left: .5rem;
            font-size: .65rem; font-weight: 700;
            padding: .15rem .5rem;
            border-radius: 999px;
            color: #fff;
        }
        .card-poster .fa-pill {
            position: absolute; top: .5rem; left: .5rem;
            background: rgba(0,0,0,.75);
            color: #ff6a07;
            font-weight: 700; font-size: .7rem;
            padding: .2rem .45rem;
            border-radius: 999px;
            backdrop-filter: blur(4px);
            line-height: 1.3;
        }
        .fa-pill-inline {
            color: #ff6a07;
            font-weight: 700;
        }
        .card-body {
            padding: .55rem .65rem .7rem;
            display: flex; flex-direction: column; gap: .15rem;
        }
        .card-title {
            font-size: .85rem; font-weight: 700;
            line-height: 1.2;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
            color: var(--text);
        }
        .card-meta {
            font-size: .7rem; color: var(--text-dim);
        }

        /* Platform colours */
        .pf-hbo   { background: #5822c8; }
        .pf-apple { background: #1c1c1e; }
        .pf-sky   { background: #003087; }
        .pf-flix  { background: #e50914; }
        .pf-mplus { background: #0067b1; }

        /* ── Skeleton ─────────────────────────────────────────── */
        .sk-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .sk-poster {
            aspect-ratio: 2 / 3;
            background: linear-gradient(90deg, #1f2233 0%, #292c40 50%, #1f2233 100%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite linear;
        }
        .sk-line {
            height: 12px; margin: .5rem .65rem; border-radius: 4px;
            background: linear-gradient(90deg, #1f2233 0%, #292c40 50%, #1f2233 100%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite linear;
        }
        .sk-line.short { width: 50%; }
        @keyframes shimmer { 0% {background-position: 200% 0;} 100% {background-position: -200% 0;} }

        /* ── Load more / empty ────────────────────────────────── */
        .load-more-wrap { text-align: center; padding: 2rem 0 1rem; }
        .btn-load-more {
            background: var(--bg-soft);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: .55rem 1.6rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-load-more:hover { background: #232638; }
        .btn-load-more:disabled { opacity: .5; cursor: not-allowed; }
        .empty-state {
            text-align: center; padding: 4rem 1rem; color: var(--text-dim);
        }
        .empty-state i { font-size: 2.2rem; margin-bottom: .8rem; opacity: .6;}

        /* ── Modal ────────────────────────────────────────────── */
        .modal-content { background: var(--bg-soft); color: var(--text); border: 1px solid var(--border); }
        .modal-backdrop-img {
            position: relative;
            aspect-ratio: 16 / 7;
            background: #000;
            background-size: cover;
            background-position: center;
        }
        .modal-backdrop-img::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to bottom, rgba(22,24,35,0) 0%, rgba(22,24,35,.6) 70%, var(--bg-soft) 100%);
        }
        .modal-body-inner { padding: 1.2rem 1.4rem 1.4rem; }
        .modal-title-line {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem; font-weight: 700;
            margin: 0 0 .4rem;
        }
        .modal-meta-row {
            display: flex; flex-wrap: wrap; gap: .5rem .8rem;
            color: var(--text-dim); font-size: .85rem;
            margin-bottom: .8rem;
        }
        .modal-meta-row .imdb { color: var(--accent); font-weight: 700;}
        .chip {
            display: inline-block; font-size: .72rem; font-weight: 600;
            padding: .15rem .55rem; border-radius: 999px;
            background: var(--bg-card); border: 1px solid var(--border);
            color: var(--text-dim);
        }
        .chip + .chip { margin-left: .25rem; }
        .modal-overview { font-size: .95rem; line-height: 1.5; color: #d6d8e0; margin: .6rem 0 1rem; }
        .modal-overview.no-overview { color: #8b8fa8; font-style: italic; }
        .modal-section-title {
            font-size: .8rem; font-weight: 700; letter-spacing: 1px;
            color: var(--text-dim); text-transform: uppercase;
            margin: 1.1rem 0 .55rem;
        }
        .cast-row {
            display: flex; gap: .8rem; overflow-x: auto;
            padding-bottom: .4rem;
        }
        .cast-row::-webkit-scrollbar { height: 6px;}
        .cast-row::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px;}
        .cast-card {
            min-width: 100px; max-width: 100px;
            text-align: center;
        }
        .cast-card img, .cast-card .ph {
            width: 100px; height: 100px; border-radius: 50%;
            object-fit: cover; background: var(--bg-card);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-dim); font-size: 1.5rem;
            margin: 0 auto .35rem;
        }
        .cast-card .name { font-size: .75rem; font-weight: 700; line-height: 1.2;}
        .cast-card .role { font-size: .7rem; color: var(--text-dim); line-height: 1.2;}
        .trailer-wrap {
            position: relative; aspect-ratio: 16/9; width: 100%;
            border-radius: 8px; overflow: hidden; background: #000;
        }
        .trailer-wrap iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0;}
        .modal-platform-row { margin-top: 1rem; }

        /* ── Channel cards (live tab) ─────────────────────────── */
        .card-logo {
            position: relative;
            aspect-ratio: 16 / 9;
            background: linear-gradient(135deg, #1a1d2e, #12141f);
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
        }
        .card-logo img {
            max-width: 75%; max-height: 75%;
            object-fit: contain; display: block;
        }
        .card-logo .no-logo {
            display: flex; align-items: center; justify-content: center;
            color: var(--text-dim); font-size: 2rem;
        }
        .card-logo .active-dot {
            position: absolute; top: .5rem; right: .5rem;
            width: 9px; height: 9px; border-radius: 50%;
            background: #2ecc71;
            box-shadow: 0 0 6px rgba(46,204,113,.7);
        }
        .card-logo .inactive-dot {
            position: absolute; top: .5rem; right: .5rem;
            width: 9px; height: 9px; border-radius: 50%;
            background: #636578;
        }
        .card-item.is-inactive {
            opacity: .45;
            filter: grayscale(.7);
        }
        .card-item.is-inactive:hover {
            opacity: .65;
            transform: none;
            box-shadow: none;
            border-color: var(--border);
        }
        .channel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.2rem;
        }
        @media (min-width: 768px)  { .channel-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); } }
        @media (min-width: 1200px) { .channel-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); } }
        .sk-logo {
            aspect-ratio: 16 / 9;
            background: linear-gradient(90deg, #1f2233 0%, #292c40 50%, #1f2233 100%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite linear;
        }

        /* ── Year range slider ────────────────────────────────── */
        .year-range-wrap {
            background: var(--bg-soft);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: .35rem .85rem .45rem;
            min-width: 175px;
            display: flex;
            flex-direction: column;
            gap: .3rem;
            cursor: default;
        }
        .year-range-label {
            font-size: .78rem;
            font-weight: 700;
            color: var(--text-dim);
            text-align: center;
            white-space: nowrap;
            letter-spacing: .3px;
        }
        .year-range-label.is-filtered { color: var(--accent-2); }
        .year-slider-container {
            position: relative;
            height: 22px;
        }
        .year-slider-track {
            position: absolute;
            top: 50%; transform: translateY(-50%);
            left: 0; right: 0;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            pointer-events: none;
        }
        .year-slider-fill {
            position: absolute;
            height: 100%;
            background: linear-gradient(90deg, #321fdb, #5b3df5);
            border-radius: 2px;
        }
        .year-thumb {
            -webkit-appearance: none;
            appearance: none;
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0; left: 0;
            margin: 0; padding: 0;
            background: transparent;
            pointer-events: none;
            outline: none;
            border: none;
        }
        .year-thumb::-webkit-slider-thumb {
            -webkit-appearance: none;
            pointer-events: all;
            width: 16px; height: 16px;
            border-radius: 50%;
            background: #5b3df5;
            border: 2px solid #fff;
            cursor: grab;
            box-shadow: 0 1px 5px rgba(0,0,0,.5);
            transition: transform .1s ease;
        }
        .year-thumb:active::-webkit-slider-thumb { transform: scale(1.18); cursor: grabbing; }
        .year-thumb::-webkit-slider-runnable-track { background: transparent; }
        .year-thumb::-moz-range-thumb {
            pointer-events: all;
            width: 14px; height: 14px;
            border-radius: 50%;
            background: #5b3df5;
            border: 2px solid #fff;
            cursor: grab;
            box-shadow: 0 1px 5px rgba(0,0,0,.5);
        }
        .year-thumb::-moz-range-track { background: transparent; }

        @media (max-width: 576px) {
            .catalogue-title { font-size: 1.3rem; }
            .grid { gap: .8rem; }
            .toolbar { padding: .8rem 1rem; }
            .grid-wrap { padding: 0 1rem 2rem;}
            .tab-bar { padding: 1rem 1rem 0; }
        }
    </style>
</head>
<body>

{{-- ─── Header ─────────────────────────────────────────────── --}}
<div class="catalogue-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1 class="catalogue-title">CINESTRELLA</h1>
        <p class="catalogue-subtitle">Catálogo de contenido</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="stat-badge"><i class="fas fa-film me-1"></i><span id="stat-movie">…</span> películas</span>
        <span class="stat-badge"><i class="fas fa-tv me-1"></i><span id="stat-series">…</span> series</span>
        <span class="stat-badge"><i class="fas fa-broadcast-tower me-1"></i><span id="stat-live">…</span> canales</span>
    </div>
</div>

{{-- ─── Tabs ───────────────────────────────────────────────── --}}
<div class="tab-bar">
    <button type="button" class="tab-btn active" data-tab="movie"><i class="fas fa-film me-1"></i> Cine</button>
    <button type="button" class="tab-btn"        data-tab="series"><i class="fas fa-tv me-1"></i> Series</button>
    <button type="button" class="tab-btn"        data-tab="live"><i class="fas fa-broadcast-tower me-1"></i> Canales</button>
</div>

{{-- ─── Toolbar (movie/series) ─────────────────────────────── --}}
<div class="toolbar" id="grid-toolbar">
    <div class="search">
        <i class="fas fa-search"></i>
        <input type="text" id="search-input" placeholder="Buscar título...">
    </div>
    <select id="filter-platform">
        <option value="">Todas las plataformas</option>
        <option value="HBO Max">HBO Max</option>
        <option value="Apple TV">Apple TV</option>
        <option value="SkyShowtime">SkyShowtime</option>
        <option value="FlixOlé">FlixOlé</option>
        <option value="Movistar Plus+">Movistar Plus+</option>
    </select>
    <select id="filter-genre">
        <option value="">Todos los géneros</option>
    </select>
    <div class="year-range-wrap" id="year-range-wrap">
        <span class="year-range-label" id="year-range-display">Cualquier año</span>
        <div class="year-slider-container">
            <div class="year-slider-track">
                <div class="year-slider-fill" id="year-slider-fill"></div>
            </div>
            <input type="range" id="filter-year-from" class="year-thumb" step="1" min="1900" max="2026" value="1900">
            <input type="range" id="filter-year-to"   class="year-thumb" step="1" min="1900" max="2026" value="2026">
        </div>
    </div>
    <select id="filter-rating">
        <option value="">⭐ Cualquier rating</option>
        <option value="9">⭐ 9+</option>
        <option value="8">⭐ 8+</option>
        <option value="7">⭐ 7+</option>
        <option value="6">⭐ 6+</option>
    </select>
    <select id="filter-sort">
        <option value="title_asc">Orden A–Z</option>
        <option value="rating_desc">Mejor valoradas</option>
        <option value="year_desc" selected>Más recientes</option>
    </select>
</div>

{{-- ─── Grid section ───────────────────────────────────────── --}}
<div id="grid-section" class="grid-wrap">
    <div id="grid" class="grid"></div>
    <div class="load-more-wrap" id="load-more-wrap" style="display:none">
        <button type="button" class="btn-load-more" id="btn-load-more">Cargar más</button>
    </div>
    <div id="empty-state" class="empty-state" style="display:none">
        <i class="fas fa-film"></i>
        <div>No hay resultados con esos filtros.</div>
    </div>
</div>

{{-- ─── Live toolbar ────────────────────────────────────────── --}}
<div class="toolbar" id="live-toolbar" style="display:none">
    <div class="search">
        <i class="fas fa-search"></i>
        <input type="text" id="live-search-input" placeholder="Buscar canal...">
    </div>
    <select id="live-filter-category">
        <option value="">Todas las categorías</option>
    </select>
    <select id="live-filter-active">
        <option value="">Todos los estados</option>
        <option value="1">Solo activos</option>
        <option value="0">Solo inactivos</option>
    </select>
    <select id="live-filter-sort">
        <option value="category_asc">Por categoría</option>
        <option value="name_asc">Nombre A–Z</option>
        <option value="name_desc">Nombre Z–A</option>
    </select>
</div>

{{-- ─── Live grid section ───────────────────────────────────── --}}
<div id="live-section" class="grid-wrap" style="display:none">
    <div id="live-grid" class="channel-grid"></div>
    <div class="load-more-wrap" id="live-load-more-wrap" style="display:none">
        <button type="button" class="btn-load-more" id="btn-live-load-more">Cargar más</button>
    </div>
    <div id="live-empty-state" class="empty-state" style="display:none">
        <i class="fas fa-broadcast-tower"></i>
        <div>No hay canales con esos filtros.</div>
    </div>
</div>

{{-- ─── Detail modal ───────────────────────────────────────── --}}
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <button type="button" class="btn-close btn-close-white position-absolute" style="top:.8rem;right:1rem;z-index:10" data-coreui-dismiss="modal" aria-label="Cerrar"></button>
            <div id="modal-body" class="modal-body p-0"></div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/vendor/jquery-3.6.3/jquery-3.6.3.min.js') }}"></script>
<script src="{{ asset('assets/vendor/coreui/coreui.bundle.min.js') }}"></script>
<script>
const PLATFORM_CLASSES = {
    'HBO Max':        'pf-hbo',
    'Apple TV':       'pf-apple',
    'SkyShowtime':    'pf-sky',
    'FlixOlé':        'pf-flix',
    'Movistar Plus+': 'pf-mplus',
};

const URLS = {
    grid:   "{{ url('catalogo/grid') }}",
    item:   "{{ url('catalogo/item') }}",
    stats:  "{{ url('catalogo/stats') }}",
    facets: "{{ url('catalogo/facets') }}",
};

const state = {
    type:  'movie',
    page:  1,
    total: 0,
    loading: false,
};

const facetsCache = { movie: null, series: null };

const liveState = {
    page:         1,
    total:        0,
    loading:      false,
    facetsLoaded: false,
};

function platformClass(name) {
    return PLATFORM_CLASSES[name] || 'pf-mplus';
}

function escapeHtml(s) {
    return (s ?? '').toString()
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function debounce(fn, ms) {
    let t;
    return function () { clearTimeout(t); const a = arguments, c = this; t = setTimeout(() => fn.apply(c, a), ms); };
}

// ── Stats ────────────────────────────────────────────────────
function loadStats() {
    fetch(URLS.stats).then(r => r.json()).then(s => {
        $('#stat-live').text(s.live);
        $('#stat-movie').text(s.movie);
        $('#stat-series').text(s.series);
    });
}

// ── Year range slider ─────────────────────────────────────────
function updateYearSlider() {
    const $from = $('#filter-year-from');
    const $to   = $('#filter-year-to');
    const from  = parseInt($from.val());
    const to    = parseInt($to.val());
    const min   = parseInt($from.attr('min'));
    const max   = parseInt($from.attr('max'));
    const pct   = v => ((v - min) / (max - min)) * 100;

    $('#year-slider-fill').css({ left: pct(from) + '%', right: (100 - pct(to)) + '%' });

    const isFullRange = from <= min && to >= max;
    const $label = $('#year-range-display');
    if (isFullRange) {
        $label.text('Cualquier año').removeClass('is-filtered');
    } else if (from === to) {
        $label.text(from).addClass('is-filtered');
    } else {
        $label.text(`${from} – ${to}`).addClass('is-filtered');
    }

    // Keep "from" thumb on top when at max to allow dragging it left
    const fromPct = pct(from);
    $('#filter-year-from').css('z-index', fromPct >= 95 ? 5 : 3);
    $('#filter-year-to').css('z-index',   fromPct >= 95 ? 3 : 5);
}

function resetYearSlider() {
    const min = parseInt($('#filter-year-from').attr('min'));
    const max = parseInt($('#filter-year-to').attr('max'));
    $('#filter-year-from').val(min);
    $('#filter-year-to').val(max);
    updateYearSlider();
}

// ── Facets (genre + year slider) ──────────────────────────────
function applyFacets(f) {
    const $g = $('#filter-genre');
    $g.find('option:not([value=""])').remove();
    (f.genres || []).forEach(g => $g.append(`<option value="${escapeHtml(g)}">${escapeHtml(g)}</option>`));

    const years = (f.years || []).map(Number).filter(Boolean).sort((a, b) => a - b);
    if (years.length >= 2) {
        const minY = years[0];
        const maxY = years[years.length - 1];
        $('#filter-year-from, #filter-year-to').attr({ min: minY, max: maxY });
        $('#filter-year-from').val(minY);
        $('#filter-year-to').val(maxY);
        updateYearSlider();
    }
}

function loadFacets(type) {
    if (facetsCache[type]) {
        applyFacets(facetsCache[type]);
        return Promise.resolve();
    }
    return fetch(`${URLS.facets}/${type}`)
        .then(r => r.json())
        .then(f => {
            facetsCache[type] = f;
            applyFacets(f);
        });
}

// ── Grid rendering ───────────────────────────────────────────
function buildCard(item) {
    const poster = item.poster_url
        ? `<img src="${escapeHtml(item.poster_url)}" alt="${escapeHtml(item.title)}" loading="lazy">`
        : `<div class="no-poster">${escapeHtml(item.title || item.name)}</div>`;

    const rating = item.rating
        ? `<div class="rating-pill"><i class="fas fa-star"></i> ${Number(item.rating).toFixed(1)}</div>`
        : (item.rating_filmaffinity
            ? `<div class="rating-pill"><i class="fas fa-star"></i> ${Number(item.rating_filmaffinity).toFixed(1)}</div>`
            : '');

    const faRating = '';

    const platform = item.platform
        ? `<div class="platform-pill ${platformClass(item.platform)}">${escapeHtml(item.platform)}</div>`
        : '';

    const yearMeta = item.year ? `${item.year}` : '';
    const genreMeta = (item.genres && item.genres.length) ? item.genres.slice(0, 2).join(' · ') : '';
    const meta = [yearMeta, genreMeta].filter(Boolean).join(' · ');

    return `
        <div class="card-item" data-id="${item.id}">
            <div class="card-poster">
                ${poster}${rating}${faRating}${platform}
            </div>
            <div class="card-body">
                <div class="card-title">${escapeHtml(item.title || item.name)}</div>
                ${meta ? `<div class="card-meta">${escapeHtml(meta)}</div>` : ''}
            </div>
        </div>`;
}

function buildSkeletons(n) {
    let html = '';
    for (let i = 0; i < n; i++) {
        html += `<div class="sk-card"><div class="sk-poster"></div><div class="sk-line"></div><div class="sk-line short"></div></div>`;
    }
    return html;
}

function loadGrid({ append = false } = {}) {
    if (state.loading) return;
    state.loading = true;

    const yearFrom = parseInt($('#filter-year-from').val());
    const yearTo   = parseInt($('#filter-year-to').val());
    const yearMin  = parseInt($('#filter-year-from').attr('min') || '0');
    const yearMax  = parseInt($('#filter-year-to').attr('max') || '9999');

    const params = new URLSearchParams({
        q:          $('#search-input').val() || '',
        platform:   $('#filter-platform').val() || '',
        genre:      $('#filter-genre').val() || '',
        year_from:  yearFrom > yearMin ? yearFrom : '',
        year_to:    yearTo   < yearMax ? yearTo   : '',
        min_rating: $('#filter-rating').val() || '',
        sort:       $('#filter-sort').val() || 'year_desc',
        page:       state.page,
    });

    if (!append) {
        $('#grid').html(buildSkeletons(12));
        $('#load-more-wrap').hide();
        $('#empty-state').hide();
    } else {
        $('#btn-load-more').prop('disabled', true).text('Cargando...');
    }

    fetch(`${URLS.grid}/${state.type}?${params}`)
        .then(r => r.json())
        .then(res => {
            state.total = res.total;
            const html = res.data.map(buildCard).join('');

            if (append) {
                $('#grid').append(html);
            } else {
                $('#grid').html(html);
            }

            if (!append && res.data.length === 0) {
                $('#grid').empty();
                $('#empty-state').show();
            } else {
                $('#empty-state').hide();
            }

            $('#load-more-wrap').toggle(!!res.has_more);
            $('#btn-load-more').prop('disabled', false).text('Cargar más');
        })
        .catch(err => {
            console.error(err);
            $('#grid').html('');
            $('#empty-state').show().find('div').text('Error cargando el catálogo.');
        })
        .finally(() => { state.loading = false; });
}

// ── Detail modal ─────────────────────────────────────────────
function openDetail(id) {
    $('#modal-body').html(`
        <div class="modal-backdrop-img"></div>
        <div class="modal-body-inner">
            <div class="sk-line" style="width:60%;height:24px"></div>
            <div class="sk-line short"></div>
            <div class="sk-line"></div><div class="sk-line"></div>
        </div>`);

    const modalEl = document.getElementById('detailModal');
    const modal = coreui.Modal.getOrCreateInstance(modalEl);
    modal.show();

    fetch(`${URLS.item}/${id}`)
        .then(r => r.json())
        .then(renderDetail)
        .catch(err => {
            console.error(err);
            $('#modal-body').html('<div class="modal-body-inner"><div class="empty-state">No se pudo cargar el detalle.</div></div>');
        });
}

function renderDetail(d) {
    const m = d.metadata;
    const backdropHtml = (m && m.backdrop_url)
        ? `<div class="modal-backdrop-img" style="background-image:url('${m.backdrop_url}')"></div>`
        : '';

    const ratingHtml = (m && m.rating)
        ? `<span class="imdb"><i class="fas fa-star"></i> ${Number(m.rating).toFixed(1)}${(m.rating_display_votes != null) ? ` <small>(${Number(m.rating_display_votes).toLocaleString('es-ES')})</small>` : ''}</span>`
        : ((m && m.rating_filmaffinity)
            ? `<span class="imdb"><i class="fas fa-star"></i> ${Number(m.rating_filmaffinity).toFixed(1)}</span>`
            : '');

    const faRatingHtml = '';

    const yearHtml    = m && m.release_year    ? `<span>${m.release_year}</span>` : '';
    const runtimeHtml = m && m.runtime_minutes ? `<span>${m.runtime_minutes} min</span>` : '';
    const genresHtml  = m && (m.genres || []).length
        ? (m.genres || []).map(g => `<span class="chip">${escapeHtml(g)}</span>`).join(' ')
        : '';

    const overview = (m && m.overview)
        ? `<p class="modal-overview">${escapeHtml(m.overview)}</p>`
        : `<p class="modal-overview no-overview">Sin sinopsis disponible.</p>`;

    const cast = (m && (m.cast || []).length)
        ? `<div class="modal-section-title">Reparto</div>
           <div class="cast-row">${
               m.cast.map(c => `
                   <div class="cast-card">
                       ${c.profile_url
                           ? `<img src="${escapeHtml(c.profile_url)}" alt="${escapeHtml(c.name)}" loading="lazy">`
                           : `<div class="ph"><i class="fas fa-user"></i></div>`}
                       <div class="name">${escapeHtml(c.name)}</div>
                       <div class="role">${escapeHtml(c.character)}</div>
                   </div>`).join('')
           }</div>`
        : '';

    const trailer = (m && m.trailer_url)
        ? `<div class="modal-section-title">Tráiler</div>
           <div class="trailer-wrap"><iframe src="${escapeHtml(m.trailer_url)}" allow="autoplay; encrypted-media" allowfullscreen></iframe></div>`
        : '';

    const platformPill = d.platform
        ? `<span class="platform-pill ${platformClass(d.platform)}" style="position:static;display:inline-block">${escapeHtml(d.platform)}</span>`
        : '';
    const categoryPill = d.category ? `<span class="chip">${escapeHtml(d.category)}</span>` : '';

    $('#modal-body').html(`
        ${backdropHtml}
        <div class="modal-body-inner">
            <h2 class="modal-title-line">${escapeHtml((m && m.title) || d.name)}</h2>
            <div class="modal-meta-row">
                ${yearHtml}${runtimeHtml}${ratingHtml}${faRatingHtml}
                ${(m && m.imdb_id) ? `<a href="https://www.imdb.com/title/${m.imdb_id}" target="_blank" rel="noopener" class="text-decoration-none" style="color:var(--accent-2)"><i class="fas fa-external-link-alt"></i> IMDb</a>` : ''}
            </div>
            <div class="mb-2">${genresHtml}</div>
            ${overview}
            <div class="modal-platform-row">${platformPill} ${categoryPill}</div>
            ${cast}
            ${trailer}
        </div>`);
}

// ── Live facets ──────────────────────────────────────────────
function loadLiveFacets() {
    if (liveState.facetsLoaded) return Promise.resolve();
    return fetch(`${URLS.facets}/live`)
        .then(r => r.json())
        .then(f => {
            const $c = $('#live-filter-category');
            $c.find('option:not([value=""])').remove();
            (f.categories || []).forEach(c => $c.append(`<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`));
            liveState.facetsLoaded = true;
        })
        .catch(() => { liveState.facetsLoaded = true; });
}

// ── Channel card ─────────────────────────────────────────────
function buildChannelCard(ch) {
    const logo = ch.logo
        ? `<img src="${escapeHtml(ch.logo)}" alt="${escapeHtml(ch.name)}" loading="lazy">`
        : `<div class="no-logo"><i class="fas fa-tv"></i></div>`;

    const dot = ch.is_active
        ? `<div class="active-dot" title="Activo"></div>`
        : `<div class="inactive-dot" title="Inactivo"></div>`;

    const category = ch.category && ch.category !== '—'
        ? `<div class="card-meta">${escapeHtml(ch.category)}</div>`
        : '';

    return `
        <div class="card-item${ch.is_active ? '' : ' is-inactive'}">
            <div class="card-logo">${logo}${dot}</div>
            <div class="card-body">
                <div class="card-title">${escapeHtml(ch.name)}</div>
                ${category}
            </div>
        </div>`;
}

function buildLiveSkeletons(n) {
    let html = '';
    for (let i = 0; i < n; i++) {
        html += `<div class="sk-card"><div class="sk-logo"></div><div class="sk-line"></div><div class="sk-line short"></div></div>`;
    }
    return html;
}

// ── Live grid ────────────────────────────────────────────────
function loadLiveGrid({ append = false } = {}) {
    if (liveState.loading) return;
    liveState.loading = true;

    const params = new URLSearchParams({
        q:        $('#live-search-input').val() || '',
        category: $('#live-filter-category').val() || '',
        active:   $('#live-filter-active').val(),
        sort:     $('#live-filter-sort').val() || 'category_asc',
        page:     liveState.page,
    });

    if (!append) {
        $('#live-grid').html(buildLiveSkeletons(12));
        $('#live-load-more-wrap').hide();
        $('#live-empty-state').hide();
    } else {
        $('#btn-live-load-more').prop('disabled', true).text('Cargando...');
    }

    fetch(`${URLS.grid}/live?${params}`)
        .then(r => r.json())
        .then(res => {
            liveState.total = res.total;
            const html = res.data.map(buildChannelCard).join('');

            if (append) {
                $('#live-grid').append(html);
            } else {
                $('#live-grid').html(html);
            }

            if (!append && res.data.length === 0) {
                $('#live-grid').empty();
                $('#live-empty-state').show();
            } else {
                $('#live-empty-state').hide();
            }

            $('#live-load-more-wrap').toggle(!!res.has_more);
            $('#btn-live-load-more').prop('disabled', false).text('Cargar más');
        })
        .catch(err => {
            console.error(err);
            $('#live-grid').html('');
            $('#live-empty-state').show().find('div').text('Error cargando los canales.');
        })
        .finally(() => { liveState.loading = false; });
}

// ── Tabs ─────────────────────────────────────────────────────
function switchTab(type) {
    state.type = type;
    state.page = 1;
    $('.tab-btn').removeClass('active').filter(`[data-tab="${type}"]`).addClass('active');

    if (type === 'live') {
        $('#grid-toolbar').hide();
        $('#grid-section').hide();
        $('#live-toolbar').show();
        $('#live-section').show();
        loadLiveFacets().then(() => loadLiveGrid({ append: false }));
    } else {
        $('#live-toolbar').hide();
        $('#live-section').hide();
        $('#grid-toolbar').show();
        $('#grid-section').show();
        $('#filter-genre').val('');
        loadFacets(type).then(() => loadGrid({ append: false }));
    }
}

// ── Wire up ──────────────────────────────────────────────────
$(function () {
    loadStats();

    $('.tab-btn').on('click', function () { switchTab($(this).data('tab')); });

    $('#search-input').on('input', debounce(() => { state.page = 1; loadGrid({ append: false }); }, 300));

    $('#filter-platform, #filter-genre, #filter-rating, #filter-sort').on('change', () => {
        state.page = 1;
        loadGrid({ append: false });
    });

    const debouncedYearReload = debounce(() => { state.page = 1; loadGrid({ append: false }); }, 280);

    $('#filter-year-from').on('input', function () {
        const from = parseInt($(this).val());
        const to   = parseInt($('#filter-year-to').val());
        if (from > to) { $(this).val(to); }
        updateYearSlider();
        debouncedYearReload();
    });

    $('#filter-year-to').on('input', function () {
        const from = parseInt($('#filter-year-from').val());
        const to   = parseInt($(this).val());
        if (to < from) { $(this).val(from); }
        updateYearSlider();
        debouncedYearReload();
    });

    $('#btn-load-more').on('click', () => {
        state.page += 1;
        loadGrid({ append: true });
    });

    const gridSentinel = document.getElementById('load-more-wrap');
    new IntersectionObserver((entries) => {
        if (!entries[0].isIntersecting) return;
        if (state.loading) return;
        if ($('#load-more-wrap').is(':hidden')) return;
        state.page += 1;
        loadGrid({ append: true });
    }, { rootMargin: '400px 0px' }).observe(gridSentinel);

    $('#grid').on('click', '.card-item', function () {
        openDetail($(this).data('id'));
    });

    $('#live-search-input').on('input', debounce(() => { liveState.page = 1; loadLiveGrid({ append: false }); }, 300));

    $('#live-filter-category, #live-filter-active, #live-filter-sort').on('change', () => {
        liveState.page = 1;
        loadLiveGrid({ append: false });
    });

    $('#btn-live-load-more').on('click', () => {
        liveState.page += 1;
        loadLiveGrid({ append: true });
    });

    const liveSentinel = document.getElementById('live-load-more-wrap');
    new IntersectionObserver((entries) => {
        if (!entries[0].isIntersecting) return;
        if (liveState.loading) return;
        if ($('#live-load-more-wrap').is(':hidden')) return;
        liveState.page += 1;
        loadLiveGrid({ append: true });
    }, { rootMargin: '400px 0px' }).observe(liveSentinel);

    document.getElementById('detailModal').addEventListener('hidden.coreui.modal', function () {
        $('#modal-body').html('');
    });

    switchTab('movie');
});
</script>
</body>
</html>
