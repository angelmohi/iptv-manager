@extends('layouts.app')

@section('content')
<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Usuarios</h4>
            </div>
            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <a class="btn btn-outline-primary" type="button" href="{{ route('users.create') }}">
                    <i class="fas fa-plus mr-2"></i> Crear usuario
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
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Fecha de creación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $u)
                            <tr data-href="{{ route('users.edit', $u->id) }}">
                                <td>{{ $u->name }}</td>
                                <td>{{ $u->email }}</td>
                                <td>{{ $u->accessLevel->name }}</td>
                                <td>{{ $u->created_at->format('d/m/Y H:i') }}</td>
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
