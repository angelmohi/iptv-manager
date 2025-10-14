@extends('layouts.app')

@section('content')

<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Canales</h4>
            </div>
            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <form method="POST" action="{{ route('lists.update') }}">
                    <a class="btn btn-outline-primary" type="button" href="{{ route('channels.create') }}">
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
        </div>
        <hr>
        <div class="row">
            <div class="col-sm-12">
                <div class="table-responsive">
                    <table id="channels-table" class="table table-hover table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Token</th>
                                <th>Activo</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los datos se cargarán por AJAX -->
                        </tbody>
                    </table>
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


@push('scripts')
<script>
$(document).ready(function() {
    $('#channels-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('channels.index') }}',
            type: 'GET'
        },
        columns: [
            { data: 'name', name: 'name' },
            { data: 'category', name: 'category' },
            { data: 'apply_token', name: 'apply_token', orderable: false },
            { data: 'is_active', name: 'is_active' },
            { data: 'tvg_type', name: 'tvg_type' }
        ],
        order: [],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        scrollX: false,
        autoWidth: false,
        columnDefs: [
            { width: "auto", targets: 0 }, // Nombre - ancho automático
            { width: "250px", targets: 1 }, // Categoría
            { width: "80px", targets: 2, className: "text-center" }, // Token
            { width: "80px", targets: 3, className: "text-center" }, // Activo
            { width: "100px", targets: 4, className: "text-center" } // Tipo
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
            // Hacer la fila clickeable para editar
            $(row).css('cursor', 'pointer');
            $(row).attr('data-href', data.edit_url);
            $(row).attr('data-target', '_blank');
        }
    });

    // Manejar click en las filas para redireccionar
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
});

$(document).ready(function() {
    // Subir archivo seleccionado
    $('#btnSubirLista').click(function() {
        var fileInput = $('#archivoLista')[0];
        if (fileInput.files.length === 0) {
            $('#file-upload-message').text('Selecciona un archivo primero').addClass('text-danger').removeClass('text-success');
            return;
        }
        var formData = new FormData();
        formData.append('archivo', fileInput.files[0]);

        // Mostrar spinner y limpiar mensajes
        $('#loading-spinner').removeClass('d-none');
        $('#file-upload-message').text('').removeClass('text-success text-danger');

        $.ajax({
            url: '{{ route("upload.m3u") }}',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: formData,
            processData: false,
            contentType: false,
            complete: function() {
                // Ocultar spinner siempre
                $('#loading-spinner').addClass('d-none');
            },
            success: function(resp) {
                $('#file-upload-message').text('Archivo subido correctamente').addClass('text-success').removeClass('text-danger');
            },
            error: function() {
                $('#file-upload-message').text('Error subiendo archivo').addClass('text-danger').removeClass('text-success');
            }
        });
    });

    // Importar categorías
    $('#btnImportarCategorias').click(function() {
        $('#loading-spinner').removeClass('d-none');
        $('#file-upload-message').text('').removeClass('text-success text-danger');

        $.ajax({
            url: '{{ route("import.categories") }}',
            type: 'GET',
            complete: function() {
                $('#loading-spinner').addClass('d-none');
            },
            success: function(resp) {
                $('#file-upload-message').text(resp.success).addClass('text-success').removeClass('text-danger');
            },
            error: function(xhr) {
                let error = xhr.responseJSON?.error || 'Error al importar categorías';
                $('#file-upload-message').text(error).addClass('text-danger').removeClass('text-success');
            }
        });
    });

    // Importar canales
    $('#btnImportarCanales').click(function() {
        $('#loading-spinner').removeClass('d-none');
        $('#file-upload-message').text('').removeClass('text-success text-danger');

        $.ajax({
            url: '{{ route("import.channels") }}',
            type: 'GET',
            complete: function() {
                $('#loading-spinner').addClass('d-none');
            },
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
</script>

@endpush

@endsection
