@extends('layouts.app')

@section('content')
@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/difusion-epg.css') }}">
@endpush

<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold">DIFUSION EPG</h4>
        <p class="text-muted">Cambios detectados en el JSON de DIFUSION.</p>
    </div>
    <div class="col-md-6 d-flex flex-column align-items-end">
        <div class="mb-2">
            <button id="run-epg-btn" class="btn btn-outline-primary mb-3"><i class="fas fa-sync-alt mr-2"></i> Actualizar</button>
        </div>
        <div class="input-group" style="max-width: 300px;">
            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="epg-search" class="form-control border-start-0" placeholder="Buscar CasId, Cambio...">
        </div>
    </div>
</div>

<div class="row g-4" id="epg-container"></div>

<div class="d-flex justify-content-center mt-4" id="epg-pagination"></div>

@push('scripts')
    <script src="{{ asset('assets/js/difusion-epg.js') }}"></script>
@endpush

@endsection
