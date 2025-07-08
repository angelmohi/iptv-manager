@extends('layouts.app')

@section('content')

<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Editar cuenta</h4>
            </div>

            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <a class="btn btn-outline-secondary" type="button" href="{{ route('accounts.index') }}" >
                    <i class="fas fa-chevron-left mr-2"></i> Volver
                </a>
            </div>
        </div>
        <hr>
        <form method="POST" action="{{ route('accounts.update', $account->id) }}" class="mb-5">
            @csrf
            @method('PUT')
            @include('accounts._form', ['editing' => true])
        </form>
        <hr>
        <h4 class="mt-5">Token Actual</h4>
        <div class="mt-3 mb-3">
            <textarea class="form-control" id="currentToken" rows="10" readonly>{{ $account->token ?? 'No hay token disponible' }}</textarea>
            <small class="form-text text-muted">{{ $account->token_expires_at ? 'Token válido hasta: ' . $account->token_expires_at->format('d/m/Y H:i') : '' }}</small>
        </div>

        <form method="POST" action="{{ route('accounts.generate-token', $account->id) }}" class="mb-5">
            <button type="submit" class="btn btn-outline-primary" data-loading-text="Renovando...">Renovar token</button>
        </form>

        <hr>
        <h4 class="mt-5">Listas de IPTV</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Lista para Tivimate') }}</div>

                    <div class="card-body">
                        <p>Enlace de la lista: <br><strong>{{ route('lists.download.tivimate', $folder) }}</strong></p>

                        <a href="{{ route('lists.download.tivimate', $folder) }}" target="_blank" class="btn btn-outline-primary">Descargar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Lista para OTT') }}</div>

                    <div class="card-body">
                        <p>Enlace de la lista: <br><strong>{{ route('lists.download.ott', $folder) }}</strong></p>

                        <a href="{{ route('lists.download.ott', $folder) }}" target="_blank" class="btn btn-outline-primary">Descargar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
