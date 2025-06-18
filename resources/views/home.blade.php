@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">

        <div class="col-12 col-md-6">
            <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Descargas últimos 7 días</h5>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" style="max-height:300px;"></canvas>
            </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Listas</h5>
            </div>
            <div class="card-body d-flex justify-content-center">
                <canvas id="listChart" style="max-height:300px; width:100%;"></canvas>
            </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6">
            <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Descargas por Región</h5>
            </div>
            <div class="card-body">
                <canvas id="regionChart" style="max-height:300px;"></canvas>
            </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Top 10 Ciudades</h5>
            </div>
            <div class="card-body">
                <canvas id="cityChart" style="max-height:300px;"></canvas>
            </div>
            </div>
        </div>
    </div>

</div>

<script>
  const regionData   = @json($byRegion->map(fn($r)=>[$r->region, $r->total]));
  const cityDataRaw  = @json($byCity->map(fn($r)=>[$r->city, $r->total]));
  const dailyDates   = @json($last7->pluck('date')->toArray());
  const dailyTotals  = @json($last7->pluck('total')->toArray());
  const listDataRaw  = @json($byList->map(fn($d)=>[$d->list, $d->total]));

  function randomVibrant(alpha = 0.8) {
    const h = Math.floor(Math.random() * 360);
    const s = Math.floor(Math.random() * 30) + 70;
    const l = Math.floor(Math.random() * 20) + 40;
    return `hsla(${h},${s}%,${l}%,${alpha})`;
  }

  const regions      = regionData.map(d=>d[0]);
  const regionsCount = regionData.map(d=>d[1]);
  new Chart(
    document.getElementById('regionChart'),
    {
      type: 'doughnut',
      data: {
        labels: regions,
        datasets: [{
          data: regionsCount,
          backgroundColor: regions.map(() => randomVibrant(0.8)),
          borderColor: '#fff',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '50%',
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    }
  );

  const topCities   = cityDataRaw.slice(0,10);
  const cities      = topCities.map(d=>d[0]);
  const citiesCount = topCities.map(d=>d[1]);
  new Chart(
    document.getElementById('cityChart'),
    {
      type: 'doughnut',
      data: {
        labels: cities,
        datasets: [{
          data: citiesCount,
          backgroundColor: cities.map(() => randomVibrant(0.8)),
          borderColor: '#fff',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '50%',
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    }
  );

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
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    }
  );
</script>
@endsection
