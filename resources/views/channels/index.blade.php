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
                        <div class="card-header">{{ __('Canales') }}</div>

                        <div class="card-body">
                            <form class="mb-4" method="POST" action="{{ route('lists.update') }}">
                                <a href="{{ route('channels.create') }}" class="btn btn-primary">
                                    Añadir Canal
                                </a>
                                <button type="submit" class="btn btn-primary  ms-3" data-loading-text="Actualizando...">Actualizar Listas</button>
                            </form>

                            <table class="table data-table table-hover table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Categoría</th>
                                        <th>Token</th>
                                        <th>Activo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($channels as $channel)
                                        <tr>
                                            <td>{{ $channel->name }}</td>
                                            <td>{{ $channel->category->name ?? 'Sin categoría' }}</td>
                                            <td>{{ $channel->apply_token ? 'Sí' : 'No' }}</td>
                                            <td>{{ $channel->is_active ? 'Sí' : 'No' }}</td>
                                            <td>
                                                <a target="_blank" href="{{ route('channels.edit', $channel->id) }}"
                                                   class="btn btn-warning btn-sm me-2">
                                                    Editar
                                                </a>

                                                <form action="{{ route('channels.duplicate', $channel->id) }}"
                                                      method="POST"
                                                      class="d-inline me-2">
                                                    @csrf
                                                    <button type="submit"
                                                            class="btn btn-secondary btn-sm"
                                                            onclick="return confirm('¿Estás seguro de que deseas duplicar este canal?');">
                                                        Duplicar
                                                    </button>
                                                </form>

                                                <form action="{{ route('channels.destroy', $channel->id) }}"
                                                      method="POST"
                                                      class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-danger btn-sm"
                                                            onclick="return confirm('¿Estás seguro de que deseas eliminar este canal?');">
                                                        Eliminar
                                                    </button>
                                                </form>
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
