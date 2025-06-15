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
                    <div class="card">
                        <div class="card-header">{{ __('Cuentas') }}</div>

                        <div class="card-body">
                            <a href="{{ route('accounts.create') }}" class="btn btn-primary mb-3">Añadir Cuenta</a>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($accounts as $account)
                                    <tr>
                                        <td>{{ $account->username }}</td>
                                        <td>
                                            <a href="{{ route('accounts.edit', $account->id) }}"
                                                class="btn btn-warning btn-sm" style="margin-right: 10px;">
                                                Editar
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
@endsection
