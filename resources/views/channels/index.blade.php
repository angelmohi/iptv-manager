@extends('layouts.app')

@section('content')

<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">{{ $config['label'] }}</h4>
            </div>
            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <div id="toolbar-canales">
                    <form method="POST" action="{{ route('lists.update', $type) }}" class="d-inline">
                        @csrf
                        <a class="btn btn-outline-primary" type="button" href="{{ route('channels.create', $type) }}">
                            <i class="fas fa-plus mr-2"></i> Crear canal
                        </a>
                        <button type="submit" class="btn btn-outline-primary ms-3" data-loading-text="Actualizando...">
                            <i class="fas fa-sync-alt mr-2"></i> Actualizar listas
                        </button>
                        <button type="button" class="btn btn-outline-primary ms-3" data-bs-toggle="modal" data-bs-target="#modalCargarLista">
                            <i class="fas fa-upload mr-2"></i> Cargar lista
                        </button>
                    </form>
                </div>
                <div id="toolbar-categorias" class="d-none">
                    <a class="btn btn-outline-primary" href="{{ route('channel-categories.create', $type) }}">
                        <i class="fas fa-plus mr-2"></i> Crear categoría
                    </a>
                </div>
            </div>
        </div>
        <hr>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="sectionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-canales" data-coreui-toggle="tab" data-coreui-target="#pane-canales"
                        type="button" role="tab" aria-controls="pane-canales" aria-selected="true">
                    <i class="fas fa-list me-1"></i> Canales
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-categorias" data-coreui-toggle="tab" data-coreui-target="#pane-categorias"
                        type="button" role="tab" aria-controls="pane-categorias" aria-selected="false">
                    <i class="fas fa-folder me-1"></i> Categorías
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="sectionTabsContent">

            <!-- Canales -->
            <div class="tab-pane fade show active" id="pane-canales" role="tabpanel" aria-labelledby="tab-canales">
                @if($type !== 'live')
                <div class="row mt-3 align-items-center">
                    <div class="col-auto">
                        <label class="col-form-label fw-semibold">
                            <i class="fas fa-filter me-1"></i> Plataforma
                        </label>
                    </div>
                    <div class="col-sm-4 col-md-3">
                        <select id="platform-filter" class="form-select">
                            <option value="">Todas</option>
                            <option value="Apple TV">Apple TV</option>
                            <option value="HBO Max">HBO Max</option>
                            <option value="SkyShowtime">SkyShowtime</option>
                            <option value="FlixOlé">FlixOlé</option>
                            <option value="Movistar Plus+">Movistar Plus+</option>
                        </select>
                    </div>
                </div>
                @endif
                <div class="row mt-3">
                    <div class="col-sm-12">
                        <div class="table-responsive">
                            <table id="channels-table" class="table table-hover table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Categoría</th>
                                        @if($type !== 'live')
                                        <th>Plataforma</th>
                                        @endif
                                        <th>Token</th>
                                        <th>Activo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categorías -->
            <div class="tab-pane fade" id="pane-categorias" role="tabpanel" aria-labelledby="tab-categorias">
                <div class="mt-3">
                    <ul id="categories-list" class="list-group">
                        @forelse($categories as $category)
                            <li class="list-group-item d-flex justify-content-between align-items-center" data-id="{{ $category->id }}">
                                <span>
                                    {{ $category->name }}
                                    <a href="{{ route('channel-categories.edit', ['type' => $type, 'category' => $category->id]) }}"
                                       class="text-decoration-none text-primary ms-1" title="Editar categoría">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </span>
                                <span class="handle" style="font-size: 1.2rem; cursor: move;" title="Arrastra para reordenar">&#x2630;</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">No hay categorías</li>
                        @endforelse
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Cargar Lista -->
<div class="modal fade" id="modalCargarLista" tabindex="-1" aria-labelledby="modalCargarListaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCargarListaLabel">Cargar lista</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="file" id="archivoLista" class="form-control mb-3" />
        <div id="loading-spinner" class="d-none text-center my-3">
          <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
          <div>Cargando...</div>
        </div>
        <div id="file-upload-message" class="mb-3"></div>
        <button id="btnSubirLista" class="btn btn-primary">Subir</button>
        <button id="btnImportarCategorias" class="btn btn-info ms-2">Importar categorías</button>
        <button id="btnImportarCanales" class="btn btn-success ms-2">Importar canales</button>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
  .nav-tabs .nav-link {
    color: #6c757d;
  }
  .nav-tabs .nav-link.active {
    color: #321fdb;
    font-weight: 600;
  }
</style>
@endpush

@push('scripts')
<script>
// Switch toolbar buttons when tab changes
document.getElementById('tab-canales').addEventListener('shown.coreui.tab', function () {
    document.getElementById('toolbar-canales').classList.remove('d-none');
    document.getElementById('toolbar-categorias').classList.add('d-none');
});
document.getElementById('tab-categorias').addEventListener('shown.coreui.tab', function () {
    document.getElementById('toolbar-categorias').classList.remove('d-none');
    document.getElementById('toolbar-canales').classList.add('d-none');
});

$(document).ready(function() {
    $('#channels-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('channels.index', $type) }}',
            type: 'GET',
            data: function(d) {
                @if($type !== 'live')
                d.platform = $('#platform-filter').val();
                @endif
                return d;
            }
        },
        columns: [
            { data: 'name', name: 'name' },
            { data: 'category', name: 'category' },
            @if($type !== 'live')
            { data: 'platform', name: 'platform', orderable: false },
            @endif
            { data: 'apply_token', name: 'apply_token', orderable: false },
            { data: 'is_active', name: 'is_active' }
        ],
        order: [],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        scrollX: false,
        autoWidth: false,
        columnDefs: [
            { width: "auto", targets: 0 },
            { width: "200px", targets: 1 },
            @if($type !== 'live')
            { width: "150px", targets: 2 },
            { width: "80px", targets: 3, className: "text-center" },
            { width: "80px", targets: 4, className: "text-center" },
            @else
            { width: "80px", targets: 2, className: "text-center" },
            { width: "80px", targets: 3, className: "text-center" },
            @endif
        ],
        language: {
            processing: "Procesando...",
            search: "Buscar:",
            lengthMenu: "Mostrar _MENU_ registros por página",
            info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty: "Mostrando 0 a 0 de 0 registros",
            infoFiltered: "(filtrado de _MAX_ registros totales)",
            infoPostFix: "",
            loadingRecords: "Cargando...",
            zeroRecords: "No se encontraron registros",
            emptyTable: "No hay datos disponibles en la tabla",
            paginate: {
                first: "Primero",
                previous: "Anterior",
                next: "Siguiente",
                last: "Último"
            },
            aria: {
                sortAscending: ": activar para ordenar la columna de manera ascendente",
                sortDescending: ": activar para ordenar la columna de manera descendente"
            }
        },
        createdRow: function(row, data, dataIndex) {
            $(row).css('cursor', 'pointer');
            $(row).attr('data-href', data.edit_url);
            $(row).attr('data-target', '_blank');
        }
    });

    $('#platform-filter').on('change', function() {
        $('#channels-table').DataTable().ajax.reload();
    });

    $('#channels-table tbody').on('click', 'tr', function() {
        var href = $(this).attr('data-href');
        var target = $(this).attr('data-target');
        if (href) {
            if (target === '_blank') {
                window.open(href, '_blank');
            } else {
                window.location.href = href;
            }
        }
    });

    $('#btnSubirLista').click(function() {
        var fileInput = $('#archivoLista')[0];
        if (fileInput.files.length === 0) {
            $('#file-upload-message').text('Selecciona un archivo primero').addClass('text-danger').removeClass('text-success');
            return;
        }
        var formData = new FormData();
        formData.append('archivo', fileInput.files[0]);

        $('#loading-spinner').removeClass('d-none');
        $('#file-upload-message').text('').removeClass('text-success text-danger');

        $.ajax({
            url: '{{ route("upload.m3u") }}',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: formData,
            processData: false,
            contentType: false,
            complete: function() { $('#loading-spinner').addClass('d-none'); },
            success: function() {
                $('#file-upload-message').text('Archivo subido correctamente').addClass('text-success').removeClass('text-danger');
            },
            error: function() {
                $('#file-upload-message').text('Error subiendo archivo').addClass('text-danger').removeClass('text-success');
            }
        });
    });

    $('#btnImportarCategorias').click(function() {
        $('#loading-spinner').removeClass('d-none');
        $('#file-upload-message').text('').removeClass('text-success text-danger');

        $.ajax({
            url: '{{ route("import.categories") }}',
            type: 'GET',
            complete: function() { $('#loading-spinner').addClass('d-none'); },
            success: function(resp) {
                $('#file-upload-message').text(resp.success).addClass('text-success').removeClass('text-danger');
            },
            error: function(xhr) {
                let error = xhr.responseJSON?.error || 'Error al importar categorías';
                $('#file-upload-message').text(error).addClass('text-danger').removeClass('text-success');
            }
        });
    });

    $('#btnImportarCanales').click(function() {
        $('#loading-spinner').removeClass('d-none');
        $('#file-upload-message').text('').removeClass('text-success text-danger');

        $.ajax({
            url: '{{ route("import.channels") }}',
            type: 'GET',
            complete: function() { $('#loading-spinner').addClass('d-none'); },
            success: function(resp) {
                $('#file-upload-message').text(resp.success).addClass('text-success').removeClass('text-danger');
            },
            error: function(xhr) {
                let error = xhr.responseJSON?.error || 'Error al importar canales';
                $('#file-upload-message').text(error).addClass('text-danger').removeClass('text-success');
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const catList = document.getElementById('categories-list');
    if (!catList) return;
    Sortable.create(catList, {
        handle: '.handle',
        animation: 150,
        onEnd() {
            const order = Array.from(catList.children)
                .filter(li => li.dataset.id)
                .map((li, idx) => ({ id: li.dataset.id, order: idx + 1 }));
            if (!order.length) return;
            fetch("{{ route('channel-categories.reorder', $type) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ order })
            })
            .then(res => { if (!res.ok) throw new Error(); return res.json(); })
            .catch(() => alert('No se pudo actualizar el orden.'));
        }
    });
});
</script>

@endpush

@endsection
