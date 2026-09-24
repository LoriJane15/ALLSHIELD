@extends('layouts.skydash-v')
@section('title', 'Edit FR')
@section('heading', 'Edit Former Rebel')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
@endpush

@section('content')
<div class="mblrc-dashboard-container">
    {{-- Modern Hero Banner (SHIELD Brand Design) --}}
    <div class="mblrc-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.15rem;">
                <div class="mblrc-hero-icon-box">
                    <i class="mdi mdi-pencil"></i>
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        MBLRC Profile Editor &middot; {{ $fr->classified_id }}
                    </div>
                    <h1 class="mblrc-hero-title">Edit Former Rebel Profile</h1>
                    <p class="mblrc-hero-sub">
                        Update profile, address, and background details for {{ trim($fr->firstname.' '.$fr->lastname) }}.
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('mblrc.fr.show', $fr) }}" class="btn" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #ffffff; font-weight: 700; border-radius: 10px; padding: 0.55rem 1.1rem;">
                    <i class="mdi mdi-eye mr-1"></i> View Profile
                </a>
                <a href="{{ route('mblrc.fr.index') }}" class="btn" style="background: #ffffff; color: #312e81; font-weight: 800; font-size: 0.875rem; padding: 0.55rem 1.25rem; border-radius: 10px; border: none; box-shadow: 0 4px 14px rgba(0,0,0,0.18); transition: all 0.2s ease;">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('mblrc.fr.update', $fr) }}">
        @csrf
        @method('PUT')

        @include('mblrc.fr._form')

        <div class="mblrc-action-bar mt-4">
            <a href="{{ route('mblrc.fr.show', $fr) }}" class="btn btn-light" style="font-weight:700; border-radius:10px; padding:0.6rem 1.4rem; color: #475569; border: 1px solid #e2e8f0;">
                Cancel
            </a>
            <button type="submit" class="btn" style="background: #2563eb; color: #ffffff; font-weight: 800; border-radius: 10px; padding: 0.65rem 1.75rem; border: none; box-shadow: 0 4px 14px rgba(37,99,235,0.25); transition: all 0.2s ease;">
                <i class="mdi mdi-check mr-1"></i> Save Changes
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const muni = document.querySelector('[data-barangay-source]');
    if (!muni) return;
    const target = document.querySelector(muni.dataset.barangayTarget);
    if (!target) return;
    muni.addEventListener('change', async () => {
        target.innerHTML = '<option value="">Loading…</option>';
        if (!muni.value) {
            target.innerHTML = '<option value="">Select barangay</option>';
            return;
        }
        const url = `${muni.dataset.barangaySource}?municipality_id=${muni.value}`;
        const rows = await fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json());
        target.innerHTML = '<option value="">Select barangay</option>'
            + rows.map((b) => `<option value="${b.id}">${b.name}</option>`).join('');
    });
})();
</script>
@endpush
