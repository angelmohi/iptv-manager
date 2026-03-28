@extends('layouts.app')

@section('content')
<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Editar canal - {{ $config['label'] }}</h4>
            </div>

            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <a class="btn btn-outline-secondary" type="button" href="{{ route('channels.index', $type) }}" >
                    <i class="fas fa-chevron-left mr-2"></i> Volver
                </a>
            </div>
        </div>
        <hr>
        <form method="POST" action="{{ route('channels.update', ['type' => $type, 'channel' => $channel->id]) }}">
            @csrf
            @method('PUT')
            @include('channels._form', ['editing' => true])
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    var deleteUrl = "{{ route('channels.destroy', ['type' => $type, 'channel' => $channel->id]) }}";
    var duplicateUrl = "{{ route('channels.duplicate', ['type' => $type, 'channel' => $channel->id]) }}";
    $(document).ready(function() {
        $('#delete-channel').on('click', function (event) {
            event.preventDefault();
            CommonFunctions.notificationConfirmDelete(
                "¿Estás seguro de eliminar el canal \u201c{{ $channel->name }}\u201d?",
                'Eliminar',
                deleteUrl
            );
        });

        $('#duplicate-channel').on('click', function (event) {
            event.preventDefault();
            CommonFunctions.notificationConfirmPost(
                "¿Estás seguro de duplicar el canal \u201c{{ $channel->name }}\u201d?",
                'Duplicar',
                duplicateUrl,
                '#198754'
            );
        });
    });
</script>
@endpush
