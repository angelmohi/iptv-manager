@extends('layouts.app')

@section('content')
<div class="container">
    @if(isset($expiredAccounts) && $expiredAccounts->isNotEmpty())
      <div class="row mb-3">
        <div class="col-12">
          <div class="alert alert-danger" role="alert">
            <strong>Atención:</strong> Hay cuentas con token expirado.
            <ul class="mb-0 mt-2">
              @foreach($expiredAccounts as $acc)
                <li>
                  <a href="{{ route('accounts.edit', $acc->id) }}">{{ $acc->name }}</a>
                  — expiró el {{ $acc->token_expires_at->format('d/m/Y H:i') }}
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      </div>
    @endif
    <div class="row">

        <div class="col-12 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Accesos únicos (últimos 7 días)</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Listas</h5>
                </div>
                <div class="card-body d-flex justify-content-center">
                    <canvas id="listChart" style="max-height:300px; width:100%;"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-12 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Accesos únicos por lista (últimos 7 días)</h5>
                </div>
                <div class="card-body" style="height: 300px;">
                    <canvas id="accessChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de accesos por cuenta --}}
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Accesos por cuenta</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="accountTabs" role="tablist">
                        @foreach($accountLogs as $i => $account)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $i === 0 ? 'active' : '' }}"
                                        id="tab-{{ $account->id }}"
                                        data-bs-toggle="tab"
                                        data-bs-target="#pane-{{ $account->id }}"
                                        type="button" role="tab"
                                        data-logs-url="{{ route('accounts.logs', $account->id) }}"
                                        aria-controls="pane-{{ $account->id }}"
                                        aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
                                    {{ $account->name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tab-content mt-3" id="accountTabsContent">
                        @foreach($accountLogs as $i => $account)
                            <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}"
                                 id="pane-{{ $account->id }}" role="tabpanel"
                                 aria-labelledby="tab-{{ $account->id }}">
                                <div class="table-responsive position-relative">
                                  <div id="spinner-{{ $account->id }}" class="spinner-overlay d-none">
                                    <div class="spinner-border text-primary" role="status">
                                      <span class="visually-hidden">Cargando...</span>
                                    </div>
                                  </div>
                                  <table class="table table-sm table-striped table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>IP</th>
                                                <th>Lista</th>
                                                <th>Ciudad</th>
                                                <th>Región</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-{{ $account->id }}">
                                            {{-- Se llena por AJAX, el spinner overlay se muestra mientras carga --}}
                                        </tbody>
                                    </table>
                                </div>
                                <div id="pagination-{{ $account->id }}" class="d-flex justify-content-between align-items-center mt-2" style="min-height: 45px;"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>

  <style>
  .spinner-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.6);z-index:10}
  .table-responsive.position-relative{min-height: 360px;}
  </style>

<script>
  const dailyDates    = @json($last7->pluck('date')->toArray());
  const dailyTotals   = @json($last7->pluck('total')->toArray());
  const listDataRaw   = @json($byList->map(fn($d)=>[$d->list, $d->total]));
  const accessDates    = @json($accessDates);
  const accessDatasets = @json($accessDatasets);

  function randomVibrant(alpha = 0.8) {
    const h = Math.floor(Math.random() * 360);
    const s = Math.floor(Math.random() * 30) + 70;
    const l = Math.floor(Math.random() * 20) + 40;
    return `hsla(${h},${s}%,${l}%,${alpha})`;
  }

  // — Daily Chart —
  const dailyLabels = dailyDates.map(d => {
    const dt = new Date(d);
    return dt.toLocaleDateString('es-ES', { day:'numeric', month:'short' });
  });
  new Chart(
    document.getElementById('dailyChart'),
    {
      type: 'line',
      data: {
        labels: dailyLabels,
        datasets: [{
          label: 'Descargas',
          data: dailyTotals,
          fill: false,
          tension: 0.2,
          borderColor: randomVibrant(1),
          pointBackgroundColor: randomVibrant(1)
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { display: false } }
      }
    }
  );

  // — List Chart —
  const listLabels = listDataRaw.map(d => d[0]);
  const listCounts = listDataRaw.map(d => d[1]);
  new Chart(
    document.getElementById('listChart'),
    {
      type: 'pie',
      data: {
        labels: listLabels,
        datasets: [{
          data: listCounts,
          backgroundColor: listLabels.map(() => randomVibrant(0.8)),
          borderColor: '#fff',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
      }
    }
  );

  // — Access Chart —
  const accessLabels = accessDates.map(d => {
    const dt = new Date(d);
    return dt.toLocaleDateString('es-ES', { day:'numeric', month:'short' });
  });

  accessDatasets.forEach(ds => {
    const color = randomVibrant(1);
    ds.borderColor          = color;
    ds.pointBackgroundColor = color;
    ds.backgroundColor      = 'transparent';
    ds.fill                 = false;
    ds.tension              = 0.2;
  });

  new Chart(
    document.getElementById('accessChart'),
    {
      type: 'line',
      data: {
        labels: accessLabels,
        datasets: accessDatasets
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
          }
        },
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { mode: 'index', intersect: false }
        },
        interaction: { mode: 'nearest', axis: 'x', intersect: false }
      }
    }
  );

  // — Logs table with AJAX pagination —
  const logsState = {};

  function loadLogs(accountId, url, page) {
    const tbody      = document.getElementById('tbody-' + accountId);
    const pagination = document.getElementById('pagination-' + accountId);
    const spinner    = document.getElementById('spinner-' + accountId);
    const table      = tbody ? tbody.closest('table') : null;
    if (spinner) spinner.classList.remove('d-none');
    if (table) table.classList.add('opacity-50');
    pagination.innerHTML = '';

    fetch(url + '?page=' + page, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
      if (!data.data || data.data.length === 0) {
        if (spinner) spinner.classList.add('d-none');
        if (table) table.classList.remove('opacity-50');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay registros para esta cuenta.</td></tr>';
        return;
      }

      tbody.innerHTML = data.data.map(row => `
        <tr>
          <td>${row.ip ?? ''}</td>
          <td>${row.list ?? ''}</td>
          <td>${row.city ?? ''}</td>
          <td>${row.region ?? ''}</td>
          <td>${row.created_at_formatted ?? ''}</td>
        </tr>
      `).join('');

      // Pagination controls
      const from  = data.from ?? 0;
      const to    = data.to ?? 0;
      const total = data.total ?? 0;
      const currentPage = data.current_page;
      const lastPage    = data.last_page;

      const info = `<small class="text-muted">${from}–${to} de ${total}</small>`;

      let buttons = '<div class="btn-group btn-group-sm">';
      buttons += `<button class="btn btn-outline-secondary" ${currentPage <= 1 ? 'disabled' : ''}
                    onclick="loadLogs(${accountId}, '${url}', ${currentPage - 1})">‹</button>`;
      // Show at most 5 page buttons around the current page
      const start = Math.max(1, currentPage - 2);
      const end   = Math.min(lastPage, currentPage + 2);
      for (let p = start; p <= end; p++) {
        buttons += `<button class="btn btn-outline-secondary ${p === currentPage ? 'active' : ''}"
                      onclick="loadLogs(${accountId}, '${url}', ${p})">${p}</button>`;
      }
      buttons += `<button class="btn btn-outline-secondary" ${currentPage >= lastPage ? 'disabled' : ''}
                    onclick="loadLogs(${accountId}, '${url}', ${currentPage + 1})">›</button>`;
      buttons += '</div>';

      pagination.innerHTML = info + buttons;
      if (spinner) spinner.classList.add('d-none');
      if (table) table.classList.remove('opacity-50');
      logsState[accountId] = { url, page: currentPage };
    })
    .catch(() => {
      if (spinner) spinner.classList.add('d-none');
      if (table) table.classList.remove('opacity-50');
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Error al cargar los datos.</td></tr>';
    });
  }

  // Load the first visible tab on page load
  const firstTab = document.querySelector('#accountTabs .nav-link.active');
  if (firstTab) {
    const accountId = firstTab.getAttribute('data-bs-target').replace('#pane-', '');
    loadLogs(accountId, firstTab.dataset.logsUrl, 1);
  }

  // Load data when switching tabs (only if not already loaded for current page)
  document.querySelectorAll('#accountTabs .nav-link').forEach(btn => {
    btn.addEventListener('shown.bs.tab', function () {
      const accountId = btn.getAttribute('data-bs-target').replace('#pane-', '');
      const url       = btn.dataset.logsUrl;
      const state     = logsState[accountId];
      
      if (!state) {
        loadLogs(accountId, url, 1);
      }
    });
  });
</script>
@endsection
