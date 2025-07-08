<div class="row g-4">
    <div class="col-md-6">
        <label for="f-name">Nombre</label>
        <input type="text" class="form-control" id="f-name" name="name" value="{{ $account->name ?? '' }}">
    </div>
    <div class="col-md-6">
        <label for="f-username">Email</label>
        <input type="text" class="form-control" id="f-username" name="username" value="{{ $account->username ?? '' }}" @if ($editing) disabled @endif>
    </div>
    @if (!$editing)
    <div class="col-md-6">
        <label for="f-password">Contraseña</label>
        <input type="password" class="form-control" id="f-password" name="password" value="{{ $account->password ?? '' }}" @if ($editing) disabled @endif>
    </div>
    @endif
    <div class="col-md-6">
        <label for="f-parental_control">Control Parental</label>
        <select class="form-control" id="f-parental_control" name="parental_control">
            <option value="1" {{ (isset($account) && $account->parental_control) ? 'selected' : '' }}>Sí</option>
            <option value="0" {{ (isset($account) && !$account->parental_control) ? 'selected' : '' }}>No</option>
        </select>
    </div>
    <div class="col-md-12 d-flex justify-content-between">
        <button type="submit" class="btn btn-outline-primary">Guardar</button>
    </div>
</div>
