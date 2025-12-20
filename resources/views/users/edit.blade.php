@extends('layouts.app')

@section('content')
<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Editar usuario</h4>
            </div>

            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <a class="btn btn-outline-secondary" type="button" href="{{ route('users.index') }}" >
                    <i class="fas fa-chevron-left mr-2"></i> Volver
                </a>
            </div>
        </div>
        <hr>
        <form method="POST" action="{{ route('users.update', $user->id) }}">
            @method('PUT')
            @include('users._form', ['user' => $user])
        </form>

        @if ($user->id !== auth()->id())
        <hr>
        <form method="POST" action="{{ route('users.destroy', $user->id) }}" class="mt-4" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este usuario?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Eliminar usuario</button>
        </form>
        @endif
    </div>
</div>
@endsection
