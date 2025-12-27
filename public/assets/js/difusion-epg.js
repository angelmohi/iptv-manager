let epgPage = 1;
let epgSearch = '';

function fetchEpg(page = 1, search = '') {
    epgPage = page;
    $.getJSON("/difusion-epg/data", function(res) {
        let data = res.data || [];
        if (search) {
            const q = search.toLowerCase();
            data = data.filter(r => Object.values(r).join(' ').toLowerCase().includes(q));
        }
        const length = 12;
        const start = (page -1)*length;
        const pageData = data.slice(start, start+length);
        renderEpg(pageData);
        renderPagination(data.length, page, length, 'epg-pagination');

        // scroll up to the container after rendering a new page (smooth)
        const container = $('#epg-container');
        if (container.length) {
            const top = Math.max(0, container.offset().top - 80);
            $('html, body').animate({ scrollTop: top }, 300);
        }
    });
}

function renderEpg(rows) {
    const container = $('#epg-container');
    container.empty();
    if (!rows.length) {
        container.append('<div class="col-12 text-center py-5"><p class="text-muted">No se encontraron registros.</p></div>');
        return;
    }

    rows.forEach(r => {
        const baseFields = ['CasId','CodCadenaTv','Nombre','Logo','PuntoReproduccion','FormatoVideo'];
        let fieldsHtml = '';
        const isAdded = (r.Cambio || '').toString().trim().toLowerCase() === 'añadido';
        const isDeleted = (r.Cambio || '').toString().trim().toLowerCase() === 'eliminado';
        if (isAdded) {
            // For new channels show the values directly (no Antes/Después labels)
            baseFields.forEach(f => {
                const val = (r['Despues_' + f] || r[f] || '').toString().trim();
                if (!val) return;
                fieldsHtml += `<div class="field-row"><div class="label-small">${f}</div>`+
                    `<div class="change-field"><div class="after">${val}</div></div></div>`;
            });
        } else if (isDeleted) {
            // For deleted channels show the previous values directly (no Antes/Después labels)
            baseFields.forEach(f => {
                const val = (r['Antes_' + f] || r[f] || '').toString().trim();
                if (!val) return;
                fieldsHtml += `<div class="field-row"><div class="label-small">${f}</div>`+
                    `<div class="change-field"><div class="after">${val}</div></div></div>`;
            });
        } else {
            baseFields.forEach(f => {
                const before = (r['Antes_' + f] || '').toString().trim();
                const after = (r['Despues_' + f] || '').toString().trim();
                if (!before && !after) return;
                fieldsHtml += `<div class="field-row"><div class="label-small">${f}</div>`+
                    `<div class="change-field"><div class="before">Antes: ${before || '<span class="text-muted">(vacío)</span>'}</div>`+
                    `<div class="after">Después: ${after || '<span class="text-muted">(vacío)</span>'}</div></div></div>`;
            });
        }

        if (!fieldsHtml) {
            fieldsHtml = `<div class="text-muted"><small>No hay campos modificados para este registro.</small></div>`;
        }

        const title = (r['Despues_Nombre'] || r['Antes_Nombre'] || r['Nombre'] || r['CodCadenaTv'] || r.CasId || '').toString().trim();

        // choose badge color by Cambio
        const cambioKey = (r.Cambio || '').toString().trim().toLowerCase();
        let badgeClass = 'bg-light text-dark border change-badge';
        if (cambioKey === 'añadido' || cambioKey === 'anadido') {
            badgeClass = 'bg-success text-white change-badge';
        } else if (cambioKey === 'modificado') {
            badgeClass = 'bg-warning text-dark change-badge';
        } else if (cambioKey === 'eliminado') {
            badgeClass = 'bg-danger text-white change-badge';
        }

        const card = `
            <div class="col-md-6 col-lg-4">
                <div class="card log-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title fw-bold mb-0">${title}</h5>
                                <small class="text-muted">${r.Fecha || ''} · ${r.Origen || ''} · ${r.CasId || ''}</small>
                            </div>
                            <span class="badge ${badgeClass}">${r.Cambio || ''}</span>
                        </div>
                        ${fieldsHtml}
                    </div>
                </div>
            </div>
        `;
        container.append(card);
    });
}

function renderPagination(total, current, length, containerId) {
    const totalPages = Math.ceil(total / length);
    const container = $('#' + containerId);
    container.empty();
    if (totalPages <= 1) return;
    let html = '<nav><ul class="pagination pagination-sm">';
    html += `<li class="page-item ${current === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="event.preventDefault(); fetchEpg(${current-1}, epgSearch);">Anterior</a></li>`;
    let startPage = Math.max(1, current - 2);
    let endPage = Math.min(totalPages, current + 2);
    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" onclick="event.preventDefault(); fetchEpg(${i}, epgSearch);">${i}</a></li>`;
    }
    html += `<li class="page-item ${current === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="event.preventDefault(); fetchEpg(${current+1}, epgSearch);">Siguiente</a></li>`;
    html += '</ul></nav>';
    container.append(html);
}

$(function(){
    fetchEpg();
    let t;
    $('#epg-search').on('keyup', function(){ clearTimeout(t); epgSearch = $(this).val(); t = setTimeout(()=> fetchEpg(1, epgSearch), 300); });
    
    // Run export command via system confirmation (CommonFunctions)
    $('#run-epg-btn').on('click', function(e){
        e.preventDefault();
        const btn = $(this);
        CommonFunctions.notificationConfirmPost(
            '¿Actualizar DIFUSION EPG ahora? Esto puede tardar unos segundos.',
            'Ejecutar',
            '/difusion-epg/run',
            '#0d6efd',
            null,
            function(responseCustom) {
                if (!responseCustom) return;
                const success = responseCustom.success === true;
                CommonFunctions.notification(success ? 'success' : 'error', responseCustom.message || (success ? 'Export ejecutado correctamente.' : 'Export finalizado con errores.'));
                // refresh list
                fetchEpg(1, epgSearch);
                // restore button state if needed
                btn.attr('disabled', false).text('Ejecutar export');
            }
        );
    });
});
