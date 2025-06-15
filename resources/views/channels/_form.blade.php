<div class="col-lg col-md-12">
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-name">Nombre</label>
                    <input type="text" class="form-control" id="f-name" name="name" value="{{ $channel->name ?? '' }}">
                </div>
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-category_id">Categoría</label>
                    <select class="form-control select2" id="f-category_id" name="category_id">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ (isset($channel) && $channel->category_id == $category->id) ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-tvg_id">TVG ID</label>
                    <input type="text" class="form-control" id="f-tvg_id" name="tvg_id" value="{{ $channel->tvg_id ?? '' }}">
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12 col-sm-12 col-md-6 form-group">
                    <label for="f-logo">Logo</label>
                    <textarea class="form-control" id="f-logo" name="logo">{{ $channel->logo ?? '' }}</textarea>
                </div>
                <div class="col-12 col-sm-12 col-md-6 form-group">
                    <label for="f-url_channel">MPD</label>
                    <textarea class="form-control" id="f-url_channel" name="url_channel">{{ $channel->url_channel ?? '' }}</textarea>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12 col-sm-12 col-md-12 form-group">
                    <label for="f-api_key">Keys</label>
                    <textarea class="form-control" id="f-api_key" name="api_key">{{ $channel->api_key ?? '' }}</textarea>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-user_agent">User Agent</label>
                    <input type="text" class="form-control" id="f-user_agent" name="user_agent" value="{{ $channel->user_agent ?? '' }}">
                </div>
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-manifest_type">Manifest Type</label>
                    <input type="text" class="form-control" id="f-manifest_type" name="manifest_type" value="{{ $channel->manifest_type ?? '' }}">
                </div>
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-license_type">License Type</label>
                    <input type="text" class="form-control" id="f-license_type" name="license_type" value="{{ $channel->license_type ?? '' }}">
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-catchup">Catchup</label>
                    <input type="text" class="form-control" id="f-catchup" name="catchup" value="{{ $channel->catchup ?? '' }}">
                </div>
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-catchup_days">Catchup Days</label>
                    <input type="text" class="form-control" id="f-catchup_days" name="catchup_days" value="{{ $channel->catchup_days ?? '' }}">
                </div>
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-catchup_source">Catchup Source</label>
                    <input type="text" class="form-control" id="f-catchup_source" name="catchup_source" value="{{ $channel->catchup_source ?? '' }}">
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-is_active">Activo</label>
                    <select class="form-control" id="f-is_active" name="is_active">
                        <option value="1" {{ (isset($channel) && $channel->is_active) ? 'selected' : '' }}>Sí</option>
                        <option value="0" {{ (isset($channel) && !$channel->is_active) ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div class="col-12 col-sm-12 col-md-4 form-group">
                    <label for="f-apply_token">Token</label>
                    <select class="form-control" id="f-apply_token" name="apply_token">
                        <option value="1" {{ (isset($channel) && $channel->apply_token) ? 'selected' : '' }}>Sí</option>
                        <option value="0" {{ (isset($channel) && !$channel->apply_token) ? 'selected' : '' }}>No</option>
                    </select>
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