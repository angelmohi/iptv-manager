@csrf
<div class="row g-4">
    <div class="col-md-6">
        <label for="f-name">Nombre</label>
        <input type="text" class="form-control" id="f-name" name="name" value="{{ $user->name ?? '' }}" required>
    </div>
    <div class="col-md-6">
        <label for="f-email">Email</label>
        <input type="email" class="form-control" id="f-email" name="email" value="{{ $user->email ?? '' }}" required>
    </div>
    <div class="col-md-6">
        <label for="f-password">Contraseña {{ isset($user) ? '(Dejar en blanco para no cambiar)' : '' }}</label>
        <input type="password" class="form-control" id="f-password" name="password" {{ isset($user) ? '' : 'required' }}>
    </div>
    <div class="col-md-6">
        <label for="f-access_level_id">Rol</label>
        <select class="form-control" id="f-access_level_id" name="access_level_id" required>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ (isset($user) && $user->access_level_id == $role->id) ? 'selected' : '' }}>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-12 d-flex justify-content-between">
        <button type="submit" class="btn btn-outline-primary">Guardar</button>
    </div>
</div>
