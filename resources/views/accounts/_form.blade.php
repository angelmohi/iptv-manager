<div class="col-lg col-md-12">
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-sm-12 col-md-6 form-group">
                    <label for="f-username">Usuario</label>
                    <input type="text" class="form-control" id="f-username" name="username" value="{{ $account->username ?? '' }}" @if ($editing) disabled @endif>
                </div>
                @if (!$editing)
                <div class="col-12 col-sm-12 col-md-6 form-group">
                    <label for="f-password">Contraseña</label>
                    <input type="password" class="form-control" id="f-password" name="password" value="{{ $account->password ?? '' }}" @if ($editing) disabled @endif>
                </div>
                @endif
            </div>
            @if (!$editing)
            <div class="row mt-3">
                <div class="col-12 col-sm-12 col-md-6 form-group">
                    <button type="submit" class="btn btn-success btn-block" name="action" value="save" data-loading-text="Guardando">Guardar</button>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>