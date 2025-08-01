@extends('layouts.app')

@section('content')
<div class="container">
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
                <div class="card-body">
                    <canvas id="accessChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

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
</script>
@endsection
