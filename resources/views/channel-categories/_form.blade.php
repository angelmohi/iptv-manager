<div class="col-lg col-md-12">
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-sm-12 col-md-6 form-group">
                    <label for="f-name">Nombre</label>
                    <input type="text" class="form-control" id="f-name" name="name" value="{{ $category->name ?? '' }}">
                </div>
                <div class="col-12 col-sm-12 col-md-6 form-group">
                    <label for="f-order">Posición</label>
                    <input type="text" class="form-control" id="f-order" name="order" value="{{ $category->order ?? '' }}">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12 col-sm-12 col-md-6 form-group">
                    <button type="submit" class="btn btn-success btn-block" name="action" value="save" data-loading-text="Guardando">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>