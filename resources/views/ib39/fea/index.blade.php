@extends('layouts.skydash-v')
@section('title', 'FEA Processing')
@section('heading', 'FEA Processing')

@push('styles')
<style>
    .fea-card{border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 4px 16px rgba(23,43,77,.05);overflow:hidden}.fea-hero{background:linear-gradient(125deg,#173b74,#2f6fed);border-radius:16px;color:#fff;padding:1.5rem}.fea-hero h2{color:#fff;font-size:1.55rem;font-weight:700}.fea-hero p{font-size:.8rem;opacity:.86}.fea-table{margin:0}.fea-table th{background:#f7f9fc;border:0;color:#718096;font-size:.66rem;letter-spacing:.04em;padding:.85rem;text-transform:uppercase;white-space:nowrap}.fea-table td{border-color:#edf1f6;color:#52616f;font-size:.78rem;padding:.9rem;vertical-align:middle}.fea-reference{color:#244a83;font-weight:700}.fea-status{background:#fff5df;border-radius:14px;color:#8a6200;display:inline-block;font-size:.65rem;font-weight:700;padding:.3rem .55rem}.fea-access{max-width:235px}.fea-access strong{color:#8a3c24;display:block;font-size:.7rem}.fea-access small{color:#718096}.fea-empty{color:#718096;padding:3rem;text-align:center}.fea-footer{border-top:1px solid #edf1f6;padding:1rem}
</style>
@endpush

@section('content')
<div class="fea-page">
    <header class="fea-hero mb-4">
        <h2 class="mb-1">Firearms, Explosives, and Ammunition Processing</h2>
        <p class="mb-0">Preliminary 39th IB queue for surfaced FR records with a recorded firearms indicator of Yes.</p>
    </header>

    <section class="card fea-card" aria-label="FEA processing queue">
        <div class="table-responsive">
            <table class="table fea-table">
                <thead><tr><th>FR Reference</th><th>Category</th><th>Surfacing Date</th><th>Surfacing Area</th><th>Overall FEA Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($processings as $fea)
                    @php($record = $fea->surfacedFormerRebel)
                    <tr>
                        <td class="fea-reference">{{ $record->reference_number }}</td>
                        <td>{{ $record->category->value }}</td>
                        <td>{{ $record->surfaced_at->format('M d, Y') }}</td>
                        <td>{{ $record->barangay?->name ? $record->barangay->name.', ' : '' }}{{ $record->municipality->name }}, {{ $record->province }}</td>
                        <td><span class="fea-status">{{ $fea->overallStatus()->value }}</span></td>
                        <td class="fea-access">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('ib39.fea.show', $fea) }}">View FEA Record</a>
                            @unless($readinessByProcessing[$fea->id])<small class="d-block mt-2">{{ $readiness->denialMessage() }}</small>@endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="fea-empty">No surfaced FR records currently require FEA processing.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($processings->hasPages())
            <div class="fea-footer">{{ $processings->links() }}</div>
        @endif
    </section>
</div>
@endsection
