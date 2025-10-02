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
</script>
@endpush

@endsection
