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
                <div class="col-md-12">
                    <a href="{{ route('accounts.index') }}" class="btn btn-secondary mb-3">Volver a la lista</a>
                </div>
            </div>

            <form method="POST" action="{{ route('accounts.update', $account->id) }}" class="row mb-5">
                <input type="hidden" name="_method" value="PUT" />

                @include('accounts._form', ['editing' => true])
            </form>

            <hr>
            <h4 class="mt-5">Token Actual</h4>
            <div class="mt-3 mb-3">
                <textarea class="form-control" id="currentToken" rows="10" readonly>{{ $account->token ?? 'No hay token disponible' }}</textarea>
                <small class="form-text text-muted">{{ $account->token_expires_at ? 'Token válido hasta: ' . $account->token_expires_at->format('d/m/Y H:i') : '' }}</small>
            </div>

            <form method="POST" action="{{ route('accounts.generate-token', $account->id) }}" class="mb-5">
                <button type="submit" class="btn btn-primary" data-loading-text="Renovando...">Renovar token</button>
            </form>

            <hr>
            <h4 class="mt-5">Listas de IPTV</h4>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">{{ __('Lista para Tivimate') }}</div>
    
                        <div class="card-body">
                            <p>Enlace de la lista: <br><strong>{{ route('lists.download.tivimate', $folder) }}</strong></p>
 
                            <a href="{{ route('lists.download.tivimate', $folder) }}" target="_blank" class="btn btn-primary">Descargar</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">{{ __('Lista para OTT') }}</div>

                        <div class="card-body">
                            <p>Enlace de la lista: <br><strong>{{ route('lists.download.ott', $folder) }}</strong></p>

                            <a href="{{ route('lists.download.ott', $folder) }}" target="_blank" class="btn btn-primary">Descargar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
