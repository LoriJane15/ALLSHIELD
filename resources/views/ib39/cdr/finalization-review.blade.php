@extends('layouts.skydash-v')
@section('title', 'CDR Finalization Review')
@section('heading', 'CDR Finalization Review')

@section('content')
<div class="row justify-content-center py-3">
    <div class="col-xl-9">
        <div class="alert alert-danger border-0 rounded-16 shadow-sm p-4 mb-4" role="alert" style="background: #fef2f2; border-left: 5px solid #ef4444 !important;">
            <div class="d-flex align-items-start gap-3">
                <i class="mdi mdi-alert-octagon-outline text-danger font-24 mt-1"></i>
                <div>
                    <h2 class="h5 font-weight-bold text-danger mb-1">Final submission will complete and lock this CDR.</h2>
                    <p class="mb-0 text-secondary font-13">After confirmation, ordinary editing, draft saving, starting, and photo replacement will be unavailable. Replacement is not part of this stage.</p>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger rounded-12 mb-4">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card border-0 rounded-16 shadow-sm mb-4" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 38px; height: 38px; background: #eef2ff; color: #4338ca;">
                        <i class="mdi mdi-clipboard-text font-18"></i>
                    </div>
                    <div>
                        <h3 class="h5 font-weight-bold text-dark mb-0">Missing information review</h3>
                        <small class="text-muted">Summary of unfilled fields before finalizing</small>
                    </div>
                </div>

                @forelse($missingFields as $section => $fields)
                    <section class="mb-4 p-3 rounded-12" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <h4 class="h6 font-weight-bold text-primary mb-2">{{ $section }}</h4>
                        <ul class="mb-0 pl-3 font-13 text-secondary">
                            @foreach($fields as $field)
                                <li>{{ $field }}</li>
                            @endforeach
                        </ul>
                    </section>
                @empty
                    <div class="text-center py-4 text-muted">
                        <i class="mdi mdi-check-decagram font-36 text-success d-block mb-2"></i>
                        <p class="mb-0 font-14 font-weight-bold text-dark">No missing editable fields or applicable tables were found. Confirmation is still required.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <form method="POST" action="{{ route('ib39.cdr.finalize', $cdr) }}" class="card border-0 rounded-16 shadow-sm p-4" style="border: 1px solid #e2e8f0 !important;">
            @csrf
            <input type="hidden" name="draft_fingerprint" value="{{ $draftFingerprint }}">
            <div class="form-check mb-4 p-3 rounded-12" style="background: #fffbeb; border: 1px solid #fde68a;">
                <input class="form-check-input ml-0 mr-2" id="confirm-final" type="checkbox" name="confirmed" value="1" required style="accent-color: #ef4444; width: 18px; height: 18px;">
                <label class="form-check-label font-weight-bold text-dark font-13 pl-4" for="confirm-final">
                    I understand that this will create the immutable final copy and lock ordinary CDR changes.
                </label>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-danger font-weight-bold px-4 py-2 rounded-10 shadow-sm" type="submit" style="background-color: #dc2626; border-color: #dc2626;">
                    <i class="mdi mdi-check-all mr-1"></i> Confirm and Submit as Final
                </button>
                <a class="btn btn-light font-weight-bold px-3 py-2 rounded-10 border" href="{{ route('ib39.cdr.edit', $cdr) }}">
                    <i class="mdi mdi-arrow-left mr-1"></i> Return to editor
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

