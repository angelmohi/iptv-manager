@extends('layouts.app')

@section('content')

<div class="card mb-4">
    <div class="card-body m-2">
        <div class="d-flex justify-content-between">
            <div>
                <h4 class="card-title align-middle d-inline pt-2">Categorías de Canales</h4>
            </div>
            <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with buttons">
                <a class="btn btn-outline-primary" type="button" href="{{ route('channel-categories.create') }}">
                    <i class="fas fa-plus mr-2"></i> Crear categoría
                </a>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-sm-12">
                <ul id="categories-list" class="list-group mt-3">
                    @foreach($categories as $category)
                      <li class="list-group-item d-flex justify-content-between align-items-center"
                          data-id="{{ $category->id }}">
                        
                        <span>
                          {{ $category->name }}
                          <a href="{{ route('channel-categories.edit', $category->id) }}" class="text-decoration-none text-primary ms-1"
                             title="Editar categoría">
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
