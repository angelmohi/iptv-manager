@extends('layouts.app')

@section('content')
<div class="container py-4">
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
                            <p>Enlace de la lista: <br><strong>{{ route('lists.download.tivimate') }}</strong></p>

                            <form id="tivimateForm" class="mt-4" method="POST" action="{{ route('lists.update') }}">
                                <button type="submit" name="action" value="tivimate" class="btn btn-primary ml-4" data-loading-text="Actualizando...">Actualizar</button>
                                <a href="{{ route('lists.download.tivimate') }}" target="_blank" class="btn btn-primary ms-3">Descargar</a>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">{{ __('Lista para OTT') }}</div>

                        <div class="card-body">
                            <p>Enlace de la lista: <br><strong>{{ route('lists.download.ott') }}</strong></p>

                            <form id="ottForm" class="mt-4" method="POST" action="{{ route('lists.update') }}">
                                <button type="submit"  name="action" value="ott" class="btn btn-primary" data-loading-text="Actualizando...">Actualizar</button>
                                <a href="{{ route('lists.download.ott') }}" target="_blank" class="btn btn-primary ms-3">Descargar</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
@endsection
