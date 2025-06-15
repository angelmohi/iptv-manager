@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="row justify-content-center p-4">
    <div class="col-md-12">

      @if (session()->has('message'))
        <div class="alert alert-{{ session('message')->type }}" role="alert">
          {{ session('message')->text }}
        </div>
      @endif

      <div class="mb-3">
        <a href="{{ route('channel-categories.index') }}"
           class="btn btn-secondary">
          Volver a la lista
        </a>
      </div>

      <form method="POST"
            action="{{ route('channel-categories.update', $category->id) }}">
        @csrf
        @method('PUT')

        @include('channel-categories._form', ['editing' => true])
      </form>

      <div class="card mt-4">
        <div class="card-header">
          Canales en “{{ $category->name }}”
        </div>
        <div class="card-body">
          <ul id="channels-list" class="list-group">
            @foreach($category->channels()->orderBy('order')->get() as $channel)
              <li class="list-group-item d-flex justify-content-between align-items-center"
                  data-id="{{ $channel->id }}">
                
                <span>{{ $channel->name }}</span>

                <div class="d-flex align-items-center">
                  <a target="_blank" href="{{ route('channels.edit', $channel->id) }}"
                     class="btn btn-warning btn-sm me-2">
                    Editar
                  </a>

                  <form action="{{ route('channels.destroy', $channel->id) }}"
                        method="POST"
                        class="d-inline me-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn btn-danger btn-sm"
                            onclick="return confirm('¿Eliminar canal “{{ $channel->name }}”?');">
                      Eliminar
                    </button>
                  </form>

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
      </div>

    </div>
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
</script>
@endpush
