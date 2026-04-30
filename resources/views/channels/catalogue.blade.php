<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catálogo — {{ config('app.name') }}</title>

    <link rel="shortcut icon" href="{{ asset('assets/img/logo.png') }}" type="image/x-icon">
    <link href="https://fonts.bunny.net/css?family=nunito" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome-free-5.15.4/css/solid.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome-free-5.15.4/css/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/coreui/coreui.css') }}">
    <link href="{{ asset('assets/vendor/datatables-1.13.1/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet">

    <style>
        body { background: #f8f9fa; font-family: 'Nunito', sans-serif; }

        .catalogue-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,.3);
        }
        .catalogue-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 1px;
            background: linear-gradient(135deg, #a8c0ff 0%, #c3b5ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        .catalogue-subtitle { color: rgba(255,255,255,.5); font-size: .85rem; margin: 0; text-align:center;}
        .stat-badge {
            background: rgba(255,255,255,.1);
            color: rgba(255,255,255,.8);
            border-radius: 20px;
            padding: .2rem .8rem;
            font-size: .8rem;
        }

        .nav-tabs .nav-link { color: #6c757d; }
        .nav-tabs .nav-link.active { color: #321fdb; font-weight: 600; }

        .badge-platform { font-size: .75rem; padding: .3em .6em; }
        .badge-hbo   { background-color: #5822c8; color: #fff; }
        .badge-apple { background-color: #1c1c1e; color: #fff; }
        .badge-sky   { background-color: #003087; color: #fff; }
        .badge-flix  { background-color: #e50914; color: #fff; }
        .badge-mplus { background-color: #0067b1; color: #fff; }

        /* filas no clicables */
        #channels-live td,
        #channels-movie td,
        #channels-series td { cursor: default; }
    </style>
</head>
<body>

{{-- Header --}}
<div class="catalogue-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1 class="catalogue-title">CINESTRELLA</h1>
        <p class="catalogue-subtitle">Catálogo de contenido</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="stat-badge" id="total-live"><i class="fas fa-broadcast-tower me-1"></i>...</span>
        <span class="stat-badge" id="total-movie"><i class="fas fa-film me-1"></i>...</span>
        <span class="stat-badge" id="total-series"><i class="fas fa-tv me-1"></i>...</span>
    </div>
</div>

<div class="container-fluid px-4">
    <div class="card mb-4">
        <div class="card-body">

            {{-- Tabs --}}
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-live" data-coreui-toggle="tab"
                        data-coreui-target="#pane-live" type="button" role="tab">
                        <i class="fas fa-broadcast-tower me-1"></i> Canales
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-movie" data-coreui-toggle="tab"
                        data-coreui-target="#pane-movie" type="button" role="tab">
                        <i class="fas fa-film me-1"></i> Cine
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-series" data-coreui-toggle="tab"
                        data-coreui-target="#pane-series" type="button" role="tab">
                        <i class="fas fa-tv me-1"></i> Series
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                {{-- CANALES --}}
                <div class="tab-pane fade show active" id="pane-live" role="tabpanel">
                    <div class="table-responsive">
                        <table id="channels-live" class="table table-hover table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Activo</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- CINE --}}
                <div class="tab-pane fade" id="pane-movie" role="tabpanel">
                    <div class="row mb-3 align-items-center">
                        <div class="col-auto">
                            <label class="col-form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i> Plataforma
                            </label>
                        </div>
                        <div class="col-sm-4 col-md-3">
                            <select id="platform-filter-movie" class="form-select">
                                <option value="">Todas</option>
                                <option value="Apple TV">Apple TV</option>
                                <option value="HBO Max">HBO Max</option>
                                <option value="SkyShowtime">SkyShowtime</option>
                                <option value="FlixOlé">FlixOlé</option>
                                <option value="Movistar Plus+">Movistar Plus+</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="channels-movie" class="table table-hover table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Plataforma</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- SERIES --}}
                <div class="tab-pane fade" id="pane-series" role="tabpanel">
                    <div class="row mb-3 align-items-center">
                        <div class="col-auto">
                            <label class="col-form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i> Plataforma
                            </label>
                        </div>
                        <div class="col-sm-4 col-md-3">
                            <select id="platform-filter-series" class="form-select">
                                <option value="">Todas</option>
                                <option value="Apple TV">Apple TV</option>
                                <option value="HBO Max">HBO Max</option>
                                <option value="SkyShowtime">SkyShowtime</option>
                                <option value="FlixOlé">FlixOlé</option>
                                <option value="Movistar Plus+">Movistar Plus+</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="channels-series" class="table table-hover table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Plataforma</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/vendor/jquery-3.6.3/jquery-3.6.3.min.js') }}"></script>
<script src="{{ asset('assets/vendor/coreui/coreui.bundle.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatables-1.13.1/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatables-1.13.1/js/dataTables.bootstrap5.min.js') }}"></script>

<script>
const PLATFORM_CLASSES = {
    'HBO Max':        'badge-hbo',
    'Apple TV':       'badge-apple',
    'SkyShowtime':    'badge-sky',
    'FlixOlé':        'badge-flix',
    'Movistar Plus+': 'badge-mplus',
};

function platformBadge(name) {
    const cls = PLATFORM_CLASSES[name] ?? 'badge-mplus';
    return `<span class="badge badge-platform ${cls}">${name}</span>`;
}

function activeBadge(val) {
    return val
        ? '<span class="badge bg-success">Sí</span>'
        : '<span class="badge bg-secondary">No</span>';
}

const dtLang = {
    processing: 'Procesando...', search: 'Buscar:',
    lengthMenu: 'Mostrar _MENU_ registros',
    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
    infoEmpty: 'Sin resultados', infoFiltered: '(filtrado de _MAX_ totales)',
    zeroRecords: 'No se encontraron resultados',
    emptyTable: 'No hay datos disponibles',
    paginate: { first:'Primero', previous:'Anterior', next:'Siguiente', last:'Último' }
};

function initLive() {
    $('#channels-live').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '{{ url("catalogo/data/live") }}', type: 'GET' },
        columns: [
            { data: 'name',      name: 'name' },
            { data: 'category',  name: 'category' },
            { data: 'is_active', name: 'is_active', orderable: true,
              render: data => activeBadge(data) },
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
        autoWidth: false,
        columnDefs: [
            { width: 'auto',  targets: 0 },
            { width: '200px', targets: 1 },
            { width: '90px',  targets: 2, className: 'text-center' },
        ],
        language: dtLang,
        drawCallback: function () {
            const total = this.api().page.info().recordsTotal;
            $('#total-live').html(`<i class="fas fa-broadcast-tower me-1"></i>${total} canales`);
        }
    });
}

function initTable(type) {
    $(`#channels-${type}`).DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: `{{ url('catalogo/data') }}/${type}`,
            type: 'GET',
            data: d => { d.platform = $(`#platform-filter-${type}`).val(); return d; }
        },
        columns: [
            { data: 'name',     name: 'name' },
            { data: 'category', name: 'category' },
            { data: 'platform', name: 'platform', orderable: false,
              render: data => platformBadge(data) },
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
        autoWidth: false,
        columnDefs: [
            { width: 'auto',  targets: 0 },
            { width: '200px', targets: 1 },
            { width: '140px', targets: 2, className: 'text-center' },
        ],
        language: dtLang,
		drawCallback: function () {
			const json = this.api().ajax.json();
			if (type === 'movie') {
				const total = this.api().page.info().recordsTotal;
				$('#total-movie').html(`<i class="fas fa-film me-1"></i>${total} películas`);
			} else {
				const total = json.seriesCount ?? this.api().page.info().recordsTotal;
				$('#total-series').html(`<i class="fas fa-tv me-1"></i>${total} series`);
			}
		}
    });

    $(`#platform-filter-${type}`).on('change', function () {
        $(`#channels-${type}`).DataTable().ajax.reload();
    });
}

$(document).ready(function () {
    initLive();
    initTable('movie');
    initTable('series');
});
</script>
</body>
</html>