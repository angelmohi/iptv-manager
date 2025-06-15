@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="row justify-content-center p-4">
    <div class="col-md-12">
      @if (session()->has('message'))
        <div class="alert alert-{{ session('message')->type }}">
          {{ session('message')->text }}
        </div>
      @endif

      <div class="card">
        <div class="card-header">{{ __('Categorías de Canales') }}</div>
        <div class="card-body">
          <a href="{{ route('channel-categories.create') }}"
             class="btn btn-primary mb-3">Añadir Categoría</a>

          <ul id="categories-list" class="list-group">
            @foreach($categories as $category)
              <li class="list-group-item d-flex justify-content-between align-items-center"
                  data-id="{{ $category->id }}">
                
                <span>{{ $category->name }}</span>

                <div class="d-flex align-items-center">
                  <a href="{{ route('channel-categories.edit', $category->id) }}"
                     class="btn btn-warning btn-sm me-2">
                    Editar
                  </a>

                  <form action="{{ route('channel-categories.destroy', $category->id) }}"
                        method="POST"
                        class="d-inline me-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn btn-danger btn-sm"
                            onclick="return confirm('¿Estás seguro de que deseas eliminar esta categoría?');">
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
  #categories-list .handle:hover {
    cursor: pointer;
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('categories-list');
  Sortable.create(el, {
    handle: '.handle',
    animation: 150,
    onEnd() {
      const order = Array.from(el.children).map((li, idx) => ({
        id: li.dataset.id,
        order: idx + 1
      }));
      fetch("{{ route('channel-categories.reorder') }}", {
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
      .then(() => console.log('Orden actualizado'))
      .catch(() => alert('No se pudo actualizar el orden.'));
    }
  });
});
</script>
@endpush
