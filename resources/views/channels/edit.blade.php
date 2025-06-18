@extends('layouts.app')

@section('content')
<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Editar canal</h4>
            </div>

            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <a class="btn btn-outline-secondary" type="button" href="{{ route('channels.index') }}" >
                    <i class="fas fa-chevron-left mr-2"></i> Volver
                </a>
            </div>
        </div>
        <hr>
        <form method="POST" action="{{ route('channels.update', $channel->id) }}">
            @csrf
            @method('PUT')
            @include('channels._form', ['editing' => true])
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    var deleteUrl = "{{ route('channels.destroy', $channel->id) }}";
    var duplicateUrl = "{{ route('channels.duplicate', $channel->id) }}";
    $(document).ready(function() {
        $('#delete-channel').on('click', function (event) {
            event.preventDefault();
            CommonFunctions.notificationConfirmDelete(
                "¿Estás seguro de eliminar el canal “{{ $channel->name }}”?",
                'Eliminar',
                deleteUrl
            );
        });

        $('#duplicate-channel').on('click', function (event) {
            event.preventDefault();
            CommonFunctions.notificationConfirmPost(
                "¿Estás seguro de duplicar el canal “{{ $channel->name }}”?",
                'Duplicar',
                duplicateUrl,
                '#198754'
            );
        });
    });
</script>
@endpush

