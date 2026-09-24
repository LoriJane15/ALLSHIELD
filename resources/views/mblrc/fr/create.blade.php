@extends('layouts.skydash-v')
@section('title', 'Register FR')
@section('heading', 'Register Former Rebel')

@section('content')
<form method="POST" action="{{ route('mblrc.fr.store') }}">
    @csrf
    <div class="row">
        <div class="col-12 grid-margin">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <a href="{{ route('mblrc.fr.index') }}" class="btn btn-outline-secondary">
                    <i class="mdi mdi-arrow-left"></i> Back
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="mdi mdi-check"></i> Register
                </button>
            </div>
        </div>
    </div>

    @include('mblrc.fr._form')

    <div class="row">
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Register Former Rebel</button>
        </div>
    </div>
</form>
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
