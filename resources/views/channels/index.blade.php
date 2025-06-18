@extends('layouts.app')

@section('content')

<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Canales</h4>
            </div>
            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <form method="POST" action="{{ route('lists.update') }}">
                    <a class="btn btn-outline-primary" type="button" href="{{ route('channels.create') }}">
                        <i class="fas fa-plus mr-2"></i> Crear canal
                    </a>
                    <button type="submit" class="btn btn-outline-primary ms-3" data-loading-text="Actualizando...">
                        <i class="fas fa-sync-alt mr-2"></i> Actualizar listas
                    </button>
                </form>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-sm-12">
                <div class="table-responsive">
                    <table class="table data-table table-hover table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Token</th>
                                <th>Activo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($channels as $channel)
                                <tr data-href="{{ route('channels.edit', $channel->id) }}" data-target="_blank" style="cursor: pointer;">
                                    <td>{{ $channel->name }}</td>
                                    <td>{{ $channel->category->name ?? 'Sin categoría' }}</td>
                                    <td>{{ $channel->apply_token ? 'Sí' : 'No' }}</td>
                                    <td>{{ $channel->is_active ? 'Sí' : 'No' }}</td>
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
