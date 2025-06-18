@extends('layouts.app')

@section('content')

<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Editar categoría</h4>
            </div>

            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <a class="btn btn-outline-secondary" type="button" href="{{ route('channel-categories.index') }}" >
                    <i class="fas fa-chevron-left mr-2"></i> Volver
                </a>
            </div>
        </div>
        <hr>
        <form method="POST" action="{{ route('channel-categories.update', $category->id) }}">
            @csrf
            @method('PUT')
            @include('channel-categories._form', ['editing' => true])
        </form>
        @if ($category->channels()->count() > 0)
        <hr class="mt-4">
        <h4 class="mt-4">Canales en “{{ $category->name }}”</h4>
        <div class="mt-4">
          <ul id="channels-list" class="list-group">
            @foreach($category->channels()->orderBy('order')->get() as $channel)
              <li class="list-group-item d-flex justify-content-between align-items-center"
                  data-id="{{ $channel->id }}">
                
                <span>
                  {{ $channel->name }}
                  <a target="_blank" href="{{ route('channels.edit', $channel->id) }}" class="text-decoration-none text-primary ms-1"
                      title="Editar canal">
                    <i class="fas fa-edit"></i>
                  </a>
                </span>

                <div class="d-flex align-items-center">
                  <span class="handle"
                        style="font-size: 1.2rem; cursor: move;"
                        title="Arrastra para reordenar">
                    &#x2630;
                  </span>
                </div>
              </li>
            @endforeach
          </ul>
      </div>
      @endif
    </div>
</div>
@endsection

@push('styles')
<style>
  #channels-list .handle:hover {
    cursor: pointer;
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('channels-list');
    Sortable.create(list, {
      handle: '.handle',
      animation: 150,
      onEnd() {
        const order = Array.from(list.children).map((li, idx) => ({
          id: li.dataset.id,
          order: idx + 1
        }));

        fetch("{{ route('channels.reorder') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
              .querySelector('meta[name="csrf-token"]')
              .getAttribute('content')
          },
          body: JSON.stringify({ order })
        })
        .then(res => {
          if (!res.ok) throw new Error('Error guardando el orden');
          return res.json();
        })
        .then(() => console.log('Canales reordenados'))
        .catch(() => alert('No se pudo guardar el nuevo orden.'));
      }
    });
  });

  var deleteUrl = "{{ route('channel-categories.destroy', $category->id) }}";
  $(document).ready(function() {
      $('#delete-category').on('click', function (event) {
          event.preventDefault();
          CommonFunctions.notificationConfirmDelete(
              "¿Estás seguro de eliminar la categoría “{{ $category->name }}”?",
              'Eliminar',
              deleteUrl
          );
      });
  });
</script>
@endpush
