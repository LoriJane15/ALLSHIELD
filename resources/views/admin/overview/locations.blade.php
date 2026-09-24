@extends('layouts.skydash-h')
@section('title', 'Locations')
@section('heading', 'RCSP Locations')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .map-wrapper { padding: 20px; }
    .map-embed {
        overflow: hidden; border-radius: 20px; position: relative;
        height: 65vh; min-height: 480px; max-width: 100%;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.35);
    }
    #locationsMap { position: absolute; inset: 0; width: 100%; height: 100%; }
</style>
@endpush

@section('content')
    {{-- Full-width operational map (original showed the final_mapping iframe here) --}}
    <div class="card position-relative mb-4">
        <div class="map-wrapper">
            <div class="map-embed">
                <div id="locationsMap"></div>
            </div>
        </div>
    </div>

    {{-- RCSP barangays grouped by municipality --}}
    <div class="row">
        <div class="col-12">
            @forelse ($municipalities as $m)
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">{{ $m['name'] }}</h5>
                            <span class="badge badge-info">{{ $m['barangays']->count() }} barangay(s)</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @foreach ($m['barangays'] as $b)
                                <span class="badge badge-secondary">
                                    {{ $b->barangay?->name ?? 'Barangay #'.$b->barangay_id }} · P{{ $b->current_phase }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-muted">No RCSP locations yet.</p>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const map = L.map('locationsMap').setView([6.7497, 125.3572], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    setTimeout(() => map.invalidateSize(), 200);
    const points = @json($frPoints);
    const colors = { Active:'#22c55e', Reintegrated:'#2c4199', Inactive:'#94a3b8', 'Under Review':'#f59e0b' };
    const bounds = [];
    points.forEach(p => {
        L.circleMarker([p.lat, p.lng], { radius:7, color: colors[p.status]||'#64748b', fillColor: colors[p.status]||'#64748b', fillOpacity:.85, weight:2 })
            .bindPopup('<strong>'+p.name+'</strong><br>'+p.status+'<br>'+(p.address||''))
            .addTo(map);
        bounds.push([p.lat, p.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, { padding:[40,40] });
})();
</script>
@endpush
