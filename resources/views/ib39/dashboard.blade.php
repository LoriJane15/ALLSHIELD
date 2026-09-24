@extends('layouts.skydash-v')
@section('title', 'Dashboard')
@section('heading', '39th Infantry Battalion')

@php
    $cards = [
        ['Konsolidado', $statusCounts['Konsolidado'] ?? 0, 'konsolidado', 'mdi-flag', 'Organized NPA Influenced Areas'],
        ['Rekonsilida', $statusCounts['Rekonsilida'] ?? 0, 'rekonsilida', 'mdi-alert', 'Less Influenced Areas'],
        ['Expansion', $statusCounts['Expansion'] ?? 0, 'expansion', 'mdi-square', 'Potential Threat Areas'],
        ['Recovery', $statusCounts['Recovery'] ?? 0, 'recovery', 'mdi-plus-circle', 'Cleared Areas'],
        ["Total FR's", $stats['total_frs'], 'total-frs', 'mdi-account-group', 'Overall Former Rebels'],
    ];
@endphp

@push('styles')
<style>
    .analytics-section { padding: 1.5rem; }
    .stat-card { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,.05); transition: all .3s ease; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 4px 6px rgba(0,0,0,.1); }
    .stat-card .card-body { padding: 1.5rem; position: relative; }
    .stat-icon { position: absolute; top: 1rem; right: 1rem; font-size: 1.5rem; opacity: .25; }
    .stat-card h5 { font-size: .875rem; font-weight: 600; margin-bottom: .75rem; color: #64748b; }
    .stat-card h2 { font-size: 2rem; font-weight: 700; margin-bottom: .5rem; color: #0f172a; }
    .stat-card .description { font-size: .75rem; color: #64748b; margin: 0; }
    .konsolidado { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; }
    .rekonsilida { background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); color: #ea580c; }
    .expansion   { background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%); color: #ca8a04; }
    .recovery    { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; }
    .total-frs   { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); color: #0284c7; }
    .chart-container { margin-top: 1rem; padding: 1.5rem; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.1); position: relative; height: 460px; }
    .chart-controls { position: absolute; top: 1.5rem; right: 1.5rem; z-index: 10; }
    .chart-select { padding: .5rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; color: #0f172a; font-size: .875rem; cursor: pointer; }
    .chart-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
</style>
@endpush

@section('content')
<div class="card">
    <div class="card-body">
        <div class="analytics-section">
            <div class="row mb-4">
                @foreach ($cards as [$title, $count, $cls, $icon, $desc])
                    <div class="col-md col-6 mb-3">
                        <div class="card stat-card {{ $cls }}">
                            <div class="card-body">
                                <div class="stat-icon"><i class="mdi {{ $icon }}"></i></div>
                                <h5>{{ $title }}</h5>
                                <h2>{{ $count }}</h2>
                                <p class="description">{{ $desc }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="chart-container">
                <div class="chart-controls">
                    <select id="chartType" class="chart-select">
                        <option value="status">Status Distribution</option>
                        <option value="municipality">Municipality Distribution</option>
                    </select>
                </div>
                <canvas id="barangayChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div id="ib39Data"
     data-status='@json($statusCounts)'
     data-muni-labels='@json($perMunicipality->pluck('municipality'))'
     data-muni-values='@json($perMunicipality->pluck('frs'))'
     hidden></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const el = document.getElementById('ib39Data');
    const status = JSON.parse(el.dataset.status || '{}');
    const muniLabels = JSON.parse(el.dataset.muniLabels || '[]');
    const muniValues = JSON.parse(el.dataset.muniValues || '[]');
    const statusLabels = ['Konsolidado', 'Rekonsilida', 'Expansion', 'Recovery'];
    const statusColors = ['#ef4444', '#fb923c', '#facc15', '#22c55e'];

    const ctx = document.getElementById('barangayChart').getContext('2d');
    let chart;

    function render(type) {
        if (chart) chart.destroy();
        const isStatus = type === 'status';
        chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: isStatus ? statusLabels : muniLabels,
                datasets: [{
                    label: isStatus ? 'Barangay Status Distribution' : 'Former Rebels per Municipality',
                    data: isStatus ? statusLabels.map(l => status[l] || 0) : muniValues,
                    backgroundColor: isStatus ? statusColors : '#4527A0',
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    render('status');
    document.getElementById('chartType').addEventListener('change', (e) => render(e.target.value));
})();
</script>
@endpush
