<div class="row g-4">
    <div class="col-md-6">
        <label for="f-name">Nombre</label>
        <input type="text" class="form-control" id="f-name" name="name" value="{{ $category->name ?? '' }}">
    </div>
    <div class="col-md-6">
        <label for="f-type">Tipo</label>
        <select class="form-control" id="f-type" name="type" required>
            <option value="">Selecciona un tipo</option>
            <option value="live" {{ (isset($category) && $category->type == 'live') ? 'selected' : '' }}>Live</option>
            <option value="movie" {{ (isset($category) && $category->type == 'movie') ? 'selected' : '' }}>Películas</option>
            <option value="series" {{ (isset($category) && $category->type == 'series') ? 'selected' : '' }}>Series</option>
        </select>
    </div>
    <div class="col-md-12">
        <button type="submit" class="btn btn-outline-primary me-3">Guardar</button>
        @if ($editing)
            <a href="#" id="delete-category" class="btn btn-outline-danger">Eliminar</a>
        @endif
    </div>
</div>