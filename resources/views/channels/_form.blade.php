<div class="row g-4">
    <div class="col-12">
        <div class="row">
            <div class="col-12 col-sm-12 col-md-6 form-group">
                <label for="f-name">Nombre</label>
                <input type="text" class="form-control" id="f-name" name="name" value="{{ $channel->name ?? '' }}">
            </div>
            <div class="col-12 col-sm-12 col-md-6 form-group">
                <label for="f-tvg_id">TVG ID</label>
                <input type="text" class="form-control" id="f-tvg_id" name="tvg_id" value="{{ $channel->tvg_id ?? '' }}">
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 col-sm-12 col-md-6 form-group">
                <label for="f-tvg_type">Tipo de canal</label>
				<select class="form-control" id="f-tvg_type" name="tvg_type">
					<option value="live" {{ (isset($channel) && $channel->tvg_type === 'live') ? 'selected' : '' }}>Live</option>
					<option value="movie" {{ (isset($channel) && $channel->tvg_type === 'movie') ? 'selected' : '' }}>Movie</option>
					<option value="series" {{ (isset($channel) && $channel->tvg_type === 'series') ? 'selected' : '' }}>Series</option>
				</select>
            </div>
            <div class="col-12 col-sm-12 col-md-6 form-group">
                <label for="f-category_id">Categoría</label>
                <select class="form-control select2" id="f-category_id" name="category_id">
                    <option value="">Selecciona una categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" 
                                data-type="{{ $category->type }}"
                                {{ (isset($channel) && $channel->category_id == $category->id) ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
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
                <label for="f-pssh">PSSH</label>
                <textarea class="form-control" id="f-pssh" name="pssh">{{ $channel->pssh ?? '' }}</textarea>
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
            <div class="col-12 col-sm-12 col-md-3 form-group">
                <label for="f-is_active">Activo</label>
                <select class="form-control" id="f-is_active" name="is_active">
                    <option value="1" {{ (isset($channel) && $channel->is_active) ? 'selected' : '' }}>Sí</option>
                    <option value="0" {{ (isset($channel) && !$channel->is_active) ? 'selected' : '' }}>No</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-3 form-group">
                <label for="f-apply_token">Token</label>
                <select class="form-control" id="f-apply_token" name="apply_token">
                    <option value="1" {{ (isset($channel) && $channel->apply_token) ? 'selected' : '' }}>Sí</option>
                    <option value="0" {{ (isset($channel) && !$channel->apply_token) ? 'selected' : '' }}>No</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-3 form-group">
                <label for="f-parental_control">Control Parental</label>
                <select class="form-control" id="f-parental_control" name="parental_control">
                    <option value="1" {{ (isset($channel) && $channel->parental_control) ? 'selected' : '' }}>Sí</option>
                    <option value="0" {{ (isset($channel) && !$channel->parental_control) ? 'selected' : '' }}>No</option>
                </select>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12 col-sm-12 col-md-12 form-group d-flex justify-content-between align-items-center">
                <div>
					<button type="submit" class="btn btn-outline-primary me-3">Guardar</button>
					@if ($editing)
						<a href="#" id="duplicate-channel" class="btn btn-outline-success me-3">Duplicar</a>
						<a href="#" id="delete-channel" class="btn btn-outline-danger">Eliminar</a>
					@endif
				</div>
                @if ($editing && $channel->tvg_type == 'live')
                    <button type="button" id="check-keys" class="btn btn-outline-info">Comprobar Keys</button>
                @endif
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
/* Style Select2 to match Bootstrap form-control */
.select2-container .select2-selection--single {
    height: 42px !important;
}

.select2-container--default .select2-selection--single {
    min-height: 42px !important;
    height: 42px !important;
    padding: 0.375rem 2.25rem 0.375rem 0.75rem !important;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    display: flex;
    align-items: center;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #212529;
    line-height: 1.5;
    padding-left: 0 !important;
    padding-top: 0 !important;
    padding-right: 0 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 42px !important;
    top: 0 !important;
    right: 0.75rem !important;
}

.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #6c757d;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Store all categories on page load
    let allCategories = [];
    $('#f-category_id option').each(function() {
        allCategories.push({
            id: $(this).val(),
            text: $(this).text(),
            type: $(this).data('type')
        });
    });
    
    // Function to filter categories based on channel type
    function filterCategories() {
        const selectedType = $('#f-tvg_type').val();
        const categorySelect = $('#f-category_id');
        const currentValue = categorySelect.val();
        
        // Destroy Select2 if it exists
        if (categorySelect.hasClass('select2-hidden-accessible')) {
            categorySelect.select2('destroy');
        }
        
        // Clear all options
        categorySelect.empty();
        
        // Add filtered options
        allCategories.forEach(function(category) {
            // Always add empty option or matching type
            if (category.id === '' || !selectedType || category.type === selectedType) {
                const option = new Option(category.text, category.id, false, category.id === currentValue);
                $(option).data('type', category.type);
                categorySelect.append(option);
            }
        });
        
        // Initialize Select2 with search
        categorySelect.select2({
            placeholder: 'Selecciona una categoría',
            allowClear: true,
            width: '100%'
        });
    }
    
    // Filter on page load
    filterCategories();
    
    // Filter when type changes
    $('#f-tvg_type').on('change', function() {
        filterCategories();
    });
    
    const checkKeysBtn = document.getElementById('check-keys');
    if (checkKeysBtn) {
        checkKeysBtn.addEventListener('click', function() {
            @if($editing)
            checkKeysBtn.disabled = true;
            checkKeysBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Comprobando...';

            fetch("{{ route('channels.check-keys', $channel->id) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                checkKeysBtn.disabled = false;
                checkKeysBtn.innerHTML = 'Comprobar Keys';

                if (data.success) {
                    if (data.pssh_updated) {
                        document.getElementById('f-pssh').value = data.pssh;
                    }
                    if (data.keys_updated) {
                        document.getElementById('f-api_key').value = data.api_key;
                    }
                    
                    swal({
                        title: 'Resultado',
                        text: data.message,
                        type: data.status
                    });
                } else {
                    swal({
                        title: 'Error',
                        text: data.message || 'Error al comprobar las keys',
                        type: 'error'
                    });
                }
            })
            .catch(error => {
                checkKeysBtn.disabled = false;
                checkKeysBtn.innerHTML = 'Comprobar Keys';
                console.error('Error:', error);
                swal({
                    title: 'Error',
                    text: 'Hubo un problema al procesar la solicitud',
                    type: 'error'
                });
            });
            @endif
        });
    }
});
</script>
@endpush
