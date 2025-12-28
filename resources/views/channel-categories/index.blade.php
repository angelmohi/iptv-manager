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
        
        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs" id="categoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="live-tab" data-coreui-toggle="tab" data-coreui-target="#live" type="button" role="tab" aria-controls="live" aria-selected="true">
                    <i class="fas fa-broadcast-tower me-1"></i> Live
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="movie-tab" data-coreui-toggle="tab" data-coreui-target="#movie" type="button" role="tab" aria-controls="movie" aria-selected="false">
                    <i class="fas fa-film me-1"></i> Películas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="series-tab" data-coreui-toggle="tab" data-coreui-target="#series" type="button" role="tab" aria-controls="series" aria-selected="false">
                    <i class="fas fa-tv me-1"></i> Series
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="categoryTabsContent">
            <!-- Live Tab -->
            <div class="tab-pane fade show active" id="live" role="tabpanel" aria-labelledby="live-tab">
                <ul id="live-categories-list" class="list-group mt-3">
                    @forelse($liveCategories as $category)
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
                    @empty
                      <li class="list-group-item text-muted">No hay categorías de tipo Live</li>
                    @endforelse
                </ul>
            </div>

            <!-- Movie Tab -->
            <div class="tab-pane fade" id="movie" role="tabpanel" aria-labelledby="movie-tab">
                <ul id="movie-categories-list" class="list-group mt-3">
                    @forelse($movieCategories as $category)
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
                    @empty
                      <li class="list-group-item text-muted">No hay categorías de tipo Películas</li>
                    @endforelse
                </ul>
            </div>

            <!-- Series Tab -->
            <div class="tab-pane fade" id="series" role="tabpanel" aria-labelledby="series-tab">
                <ul id="series-categories-list" class="list-group mt-3">
                    @forelse($seriesCategories as $category)
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
                    @empty
                      <li class="list-group-item text-muted">No hay categorías de tipo Series</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
  .handle:hover {
    cursor: pointer;
  }
  
  .nav-tabs .nav-link {
    color: #6c757d;
  }
  
  .nav-tabs .nav-link.active {
    color: #321fdb;
    font-weight: 600;
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Function to create sortable instance for a list
  function createSortable(listId) {
    const el = document.getElementById(listId);
    if (!el) return;
    
    Sortable.create(el, {
      handle: '.handle',
      animation: 150,
      onEnd() {
        const order = Array.from(el.children)
          .filter(li => li.dataset.id) // Skip empty state items
          .map((li, idx) => ({
            id: li.dataset.id,
            order: idx + 1
          }));
        
        if (order.length === 0) return;
        
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
  }
  
  // Create sortable instances for each tab
  createSortable('live-categories-list');
  createSortable('movie-categories-list');
  createSortable('series-categories-list');
});
</script>
@endpush
