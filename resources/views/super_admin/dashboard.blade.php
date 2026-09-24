@extends('layouts.skydash-v')
@section('title', 'Dashboard')
@section('heading', 'Super Admin')

@push('styles')
<style>
    .icon-top-right { position: absolute; top: 1.25rem; right: 1.5rem; font-size: 2rem; opacity: .15; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    {{-- Greeting --}}
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="card-title fs-3 mb-1">Good Day! {{ $userFullname }}</h1>
                <p class="text-muted mb-0">Strengthening Institutions and Empowering Localities Against Discrimination Programs for Former Rebels</p>
            </div>
            <button class="btn btn-sm btn-light bg-white">
                <i class="mdi mdi-calendar"></i> Today is <span class="text-primary">{{ now()->format('d F Y') }}</span>
            </button>
        </div>
    </div>

    <div class="row mb-1">
        <div class="col-md-8">
            {{-- Total RCSP Barangay --}}
            <div class="card position-relative">
                <div class="card-body">
                    <h5 class="card-title">Total RCSP Barangay</h5>
                    <div class="d-flex align-items-center">
                        <h2 class="text-primary mb-0">{{ $rcsp['total'] }}</h2>
                        <p class="text-muted mb-0 ms-2">Identified RCSP Barangays</p>
                    </div>
                    <p class="small text-muted mb-0 mt-2">As of {{ now()->format('d F Y') }}</p>
                </div>
                <i class="mdi mdi-home-map-marker icon-top-right text-primary"></i>
            </div>

            <div class="mb-4"></div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card position-relative h-100">
                        <div class="card-body">
                            <h5 class="card-title fs-6">Total Not Yet RCSP Barangay</h5>
                            <h2 class="text-danger mb-1">{{ $rcsp['not_started'] }}</h2>
                            <p class="text-muted mb-0"><i class="mdi mdi-alert-circle-outline"></i></p>
                            <p class="small text-muted mb-0">As of {{ now()->format('d F Y') }}</p>
                        </div>
                        <i class="mdi mdi-cancel icon-top-right text-danger"></i>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card position-relative h-100">
                        <div class="card-body">
                            <h5 class="card-title fs-6">Total On-Going RCSP Barangay</h5>
                            <h2 class="text-warning mb-1">{{ $rcsp['ongoing'] }}</h2>
                            <p class="text-muted mb-0"><i class="mdi mdi-progress-clock"></i></p>
                            <p class="small text-muted mb-0">As of {{ now()->format('d F Y') }}</p>
                        </div>
                        <i class="mdi mdi-progress-clock icon-top-right text-warning"></i>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card position-relative h-100">
                        <div class="card-body">
                            <h5 class="card-title fs-6">Total Completed RCSP Barangay</h5>
                            <h2 class="text-success mb-1">{{ $rcsp['completed'] }}</h2>
                            <p class="text-muted mb-0"><i class="mdi mdi-check-circle-outline"></i></p>
                            <p class="small text-muted mb-0">As of {{ now()->format('d F Y') }}</p>
                        </div>
                        <i class="mdi mdi-check icon-top-right text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Notifications --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Notifications</h5>
                    @php
                        $notes = $recentDocuments->take(3);
                    @endphp
                    @forelse ($notes as $doc)
                        <div class="d-flex align-items-center mb-3">
                            <span class="d-flex align-items-center justify-content-center rounded-circle bg-primary text-white me-3" style="width:40px;height:40px;">
                                <i class="mdi mdi-file-document-outline"></i>
                            </span>
                            <div>
                                <strong>{{ $doc->rcspBarangay?->barangay?->name ?? 'RCSP Barangay' }}</strong>
                                <p class="mb-0 small">{{ $doc->phase?->name ?? 'Phase' }} · {{ ucfirst($doc->status) }}</p>
                                <small class="text-muted">{{ $doc->created_at?->diffForHumans() }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No recent activity.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- RCSP Analytics --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">RCSP Analytics</h5>
                    <p class="text-muted">RCSP barangays identified per municipality.</p>
                    <div style="height:300px;"><canvas id="rcspBarChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Document Overview --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Document Overview</h5>
                    <p class="text-muted">Track and manage document submission, revisions, and verification.</p>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>RCSP Barangay</th>
                                    <th>Date &amp; Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentDocuments as $doc)
                                    @php
                                        $badge = match ($doc->status) {
                                            'approved' => 'badge badge-success', 'disapproved' => 'badge badge-danger',
                                            'submitted', 'updated' => 'badge badge-warning', default => 'badge badge-secondary',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $doc->phase?->name ?? 'Phase' }} submission</td>
                                        <td>{{ $doc->rcspBarangay?->barangay?->name ?? '—' }}</td>
                                        <td>{{ $doc->created_at?->format('d F Y, g:i A') }}</td>
                                        <td><span class="{{ $badge }}">{{ ucfirst($doc->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">No documents yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const labels = @json($rcspByMunicipality->keys());
    const values = @json($rcspByMunicipality->values());
    new Chart(document.getElementById('rcspBarChart'), {
        type: 'bar',
        data: { labels, datasets: [{ label: 'RCSP Barangays', data: values, backgroundColor: '#6a5acd', borderRadius: 6, maxBarThickness: 80, categoryPercentage: 0.6, barPercentage: 0.7 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                   scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
})();
</script>
@endpush
