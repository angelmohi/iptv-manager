@extends('layouts.app')

@section('content')
<style>
    .log-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
        margin-bottom: 2rem;
    }
    .log-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
    .code-block {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 10px;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
        font-size: 0.85rem;
        max-height: 120px;
        overflow-y: auto;
        word-break: break-all;
        position: relative;
    }
    .copy-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        opacity: 0.5;
        transition: opacity 0.2s;
    }
    .copy-btn:hover {
        opacity: 1;
    }
    .label-small {
        font-size: 0.75rem;
        text-transform: uppercase;
        font-weight: bold;
        color: #6c757d;
        margin-bottom: 4px;
        display: block;
    }
</style>

<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold">Historial de Cambios</h4>
        <p class="text-muted">Registro detallado de actualizaciones en canales.</p>
    </div>
    <div class="col-md-6 d-flex align-items-center justify-content-md-end">
        <div class="input-group" style="max-width: 300px;">
            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="log-search" class="form-control border-start-0" placeholder="Buscar canal, PSSH o key...">
        </div>
    </div>
</div>

<div class="row g-4" id="logs-container">
    <!-- Contenido dinámico -->
</div>

<div class="d-flex justify-content-center mt-4" id="pagination-container">
    <!-- Paginación dinámica -->
</div>

@push('scripts')
<script>
let currentPage = 1;
let searchValue = '';

function fetchLogs(page = 1, search = '') {
    const length = 12;
    const start = (page - 1) * length;

    $.ajax({
        url: '{{ route('logs.index') }}',
        type: 'GET',
        data: {
            draw: 1,
            start: start,
            length: length,
            search: { value: search },
            order: [{ column: 4, dir: 'desc' }],
            columns: [
                { data: 'channel' },
                { data: 'pssh' },
                { data: 'api_key' },
                { data: 'created_by' },
                { data: 'created_at' }
            ]
        },
        success: function(response) {
            renderLogs(response.data);
            renderPagination(response.recordsFiltered, page, length);
        }
    });
}

function renderLogs(logs) {
    const container = $('#logs-container');
    container.empty();

    if (logs.length === 0) {
        container.append('<div class="col-12 text-center py-5"><p class="text-muted">No se encontraron registros.</p></div>');
        return;
    }

    logs.forEach(log => {
        const psshId = 'pssh-' + Math.random().toString(36).substr(2, 9);
        const keysId = 'keys-' + Math.random().toString(36).substr(2, 9);
        const editUrl = `{{ url('channels/edit') }}/${log.channel_id}`;

        const card = `
            <div class="col-md-6 col-lg-4">
                <div class="card log-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title fw-bold mb-0">
                                    <a href="${editUrl}" target="_blank" class="text-primary text-decoration-none hover-underline">
                                        ${log.channel} <i class="fas fa-external-link-alt ms-1 small" style="font-size: 0.75rem;"></i>
                                    </a>
                                </h5>
                                <small class="text-muted">${log.created_at}</small>
                            </div>
                            <span class="badge bg-light text-dark border">${log.created_by}</span>
                        </div>
                        
                        <div class="mb-3">
                            <span class="label-small">PSSH</span>
                            <div class="code-block" id="${psshId}">
                                ${log.pssh || '<span class="text-muted italic">N/A</span>'}
                            </div>
                        </div>

                        <div>
                            <span class="label-small">Keys</span>
                            <div class="code-block" id="${keysId}">
                                ${log.api_key || '<span class="text-muted italic">N/A</span>'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.append(card);
    });
}

function renderPagination(total, current, length) {
    const totalPages = Math.ceil(total / length);
    const container = $('#pagination-container');
    container.empty();

    if (totalPages <= 1) return;

    let html = '<nav><ul class="pagination pagination-sm">';
    
    // Previous
    html += `<li class="page-item ${current === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="changePage(${current - 1})">Anterior</a>
    </li>`;

    // Strategy for many pages
    let startPage = Math.max(1, current - 2);
    let endPage = Math.min(totalPages, current + 2);

    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === current ? 'active' : ''}">
            <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
        </li>`;
    }

    // Next
    html += `<li class="page-item ${current === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="changePage(${current + 1})">Siguiente</a>
    </li>`;

    html += '</ul></nav>';
    container.append(html);
}

function changePage(page) {
    currentPage = page;
    fetchLogs(currentPage, searchValue);
}

$(document).ready(function() {
    fetchLogs();

    let searchTimer;
    $('#log-search').on('keyup', function() {
        clearTimeout(searchTimer);
        searchValue = $(this).val();
        searchTimer = setTimeout(() => {
            currentPage = 1;
            fetchLogs(currentPage, searchValue);
        }, 500);
    });
});
</script>
@endpush

@endsection
