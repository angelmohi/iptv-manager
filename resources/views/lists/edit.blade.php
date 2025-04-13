@extends('layouts.app')

@section('content')
    <div class="row justify-content-center p-4">
        <div class="col-md-12">
            @if (session()->has('message'))
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-{{ session('message')->type }}" role="alert">
                        {{ session('message')->text }}
                    </div>
                </div>
            </div>
            @endif
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">{{ __('Lista para Tivimate') }}</div>
    
                        <div class="card-body">
                            <form id="tivimateForm" method="POST" action="{{ route('lists.update') }}">
                                <input type="file" name="tivimate_list" id="tivimateList" class="form-control mb-3" accept=".m3u">
                                <button type="submit" class="btn btn-primary ml-4" data-loading-text="Actualizando...">Actualizar</button>
                                <a href="{{ route('lists.download.tivimate') }}" target="_blank" class="btn btn-primary ms-3">Descargar</a>
                                <span class="float-end">Enlace de la lista: <strong>{{ route('lists.download.tivimate') }}</strong></span>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">{{ __('Lista para OTT') }}</div>

                        <div class="card-body">
                            <form id="ottForm" method="POST" action="{{ route('lists.update') }}">
                                <input type="file" name="ott_list" id="ottList" class="form-control mb-3" accept=".m3u">
                                <button type="submit" class="btn btn-primary" data-loading-text="Actualizando...">Actualizar</button>
                                <a href="{{ route('lists.download.ott') }}" target="_blank" class="btn btn-primary ms-3">Descargar</a>
                                <span class="float-end">Enlace de la lista: <strong>{{ route('lists.download.ott') }}</strong></span>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
@endsection
