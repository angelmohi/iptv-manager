@extends('layouts.app')

@section('content')

<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Cuentas</h4>
            </div>
            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <a class="btn btn-outline-primary" type="button" href="{{ route('accounts.create') }}">
                    <i class="fas fa-plus mr-2"></i> Crear cuenta
                </a>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-sm-12">
                <div class="table-responsive">
                    <table class="table table-striped" style="cursor: pointer;">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Fecha de expiración del token</th>
                                <th>Fecha de creación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $account)
                            <tr data-href="{{ route('accounts.edit', $account->id) }}">
                                <td>{{ $account->username }}</td>
                                <td>
                                    @if ($account->token_expires_at)
                                        {{ $account->token_expires_at->format('d/m/Y H:i') }}
                                    @else
                                        <span class="text-muted">No hay token disponible</span>
                                    @endif
                                </td>
                                <td>{{ $account->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
