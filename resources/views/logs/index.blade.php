@extends('layouts.app')

@section('content')
<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Historial de Cambios</h4>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-sm-12">
                <div class="table-responsive">
                    <table id="logs-table" class="table table-hover table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Canal</th>
                                <th>PSSH</th>
                                <th>Keys</th>
                                <th>Realizado por</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
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
    $('#logs-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('logs.index') }}',
            type: 'GET'
        },
        columns: [
            { data: 'channel', name: 'channel' },
            { data: 'pssh', name: 'pssh' },
            { data: 'api_key', name: 'api_key' },
            { data: 'created_by', name: 'created_by' },
            { data: 'created_at', name: 'created_at' }
        ],
        order: [[4, 'desc']],
        autoWidth: false,
        columnDefs: [
            { width: '15%', targets: 0 },
            { width: '25%', targets: 1 },
            { width: '35%', targets: 2 },
            { width: '10%', targets: 3 },
            { width: '15%', targets: 4 }
        ],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: "Procesando...",
            search: "Buscar:",
            lengthMenu: "Mostrar _MENU_ registros por página",
            info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty: "Mostrando 0 a 0 de 0 registros",
            infoFiltered: "(filtrado de _MAX_ registros totales)",
            loadingRecords: "Cargando...",
            zeroRecords: "No se encontraron registros",
            emptyTable: "No hay datos disponibles en la tabla",
            paginate: {
                first: "Primero",
                previous: "Anterior",
                next: "Siguiente",
                last: "Último"
            }
        }
    });
});
</script>
@endpush

@endsection
