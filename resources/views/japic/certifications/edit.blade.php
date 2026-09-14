@extends('layouts.skydash-v')
@section('title', 'Edit JAPIC Certification Draft')
@section('heading', 'Certification Draft Editor')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .fixed-narrative {
        font-family: "Georgia", "Times New Roman", serif;
        font-size: 1.05rem;
        line-height: 2.2;
        text-align: justify;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        color: #1e293b;
    }
    .fixed-narrative input {
        display: inline-block;
        border: none;
        border-bottom: 2px solid #312e81;
        border-radius: 0;
        background: #ffffff;
        padding: 0.2rem 0.6rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0.2rem;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .fixed-narrative input:focus {
        border-bottom-color: #2563eb;
        background: #eff6ff;
        outline: none;
    }
    .personnel-row {
        padding: 1rem 1.25rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        margin-bottom: 0.75rem;
        transition: all 0.2s ease;
    }
    .personnel-row:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 2px 6px rgba(15,23,42,0.04);
    }
</style>
@endpush

@section('content')
@php
    $certificate = $payload['certificate'];
    $narrative = $certificate['narrative_values'];
    $prepared = old('certificate.prepared_by', $certificate['prepared_by'] ?: [['full_name' => '', 'rank' => '']]);
    $attested = old('certificate.attested_by', $certificate['attested_by'] ?: [['full_name' => '', 'rank' => '']]);
@endphp

<div class="mblrc-dashboard-container">
    {{-- Header / Breadcrumb Bar --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4" style="gap: 1rem;">
        <div>
            <a href="{{ route('japic.certifications.show', $processing) }}" class="btn btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; color: #475569; font-weight: 750; border-radius: 8px; padding: 0.45rem 1rem;">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Certification Profile
            </a>
        </div>
        <div>
            <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 750; font-size: 0.8125rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #c7d2fe;">
                <i class="mdi mdi-lock mr-1"></i> Encrypted Immutable Revision
            </span>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success" role="status" style="border-radius: 10px; font-weight: 600;">
            <i class="mdi mdi-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="border-radius: 10px;">
            <ul class="mb-0 font-weight-bold">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 1. Photo Section Card --}}
    <div class="mblrc-form-card">
        <div class="mblrc-form-card-header">
            <div class="d-flex align-items-center gap-3">
                <div class="mblrc-form-header-icon" style="background: #eef2ff; color: #312e81;">
                    <i class="mdi mdi-camera-account"></i>
                </div>
                <div>
                    <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Private Certification Photograph</h4>
                    <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Encrypted photograph for the official JAPIC certification</p>
                </div>
            </div>
        </div>
        <div class="mblrc-form-card-body">
            @if($processing->currentPhotoVersion)
                <div class="d-flex align-items-center gap-3 p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <i class="mdi mdi-check-circle-outline" style="font-size: 1.5rem; color: #2563eb;"></i>
                    <div class="flex-grow-1">
                        <strong style="color: #0f172a;">Active Photograph: Version {{ $processing->currentPhotoVersion->version_number }}</strong>
                        <div class="text-muted small">{{ $processing->currentPhotoVersion->width }} &times; {{ $processing->currentPhotoVersion->height }} pixels</div>
                    </div>
                    <a href="{{ route('japic.certifications.photos.show', [$processing, $processing->currentPhotoVersion]) }}" class="btn btn-sm btn-outline-primary" style="font-weight: 700; border-radius: 8px;">
                        <i class="mdi mdi-eye-outline mr-1"></i> View Securely
                    </a>
                </div>
            @else
                <div class="p-3 mb-3 rounded-3 text-muted" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                    <i class="mdi mdi-information-outline mr-1"></i> No JAPIC certification photograph has been selected. The frozen CDR photograph will be used when available.
                </div>
            @endif

            <form method="POST" enctype="multipart/form-data" action="{{ route('japic.certifications.photos.store', $processing) }}" class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                @csrf
                <input type="hidden" name="revision" value="{{ $processing->draft?->revision ?? 0 }}">
                <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label for="certification-photo" class="mblrc-label">Upload New Photograph (JPEG or PNG)</label>
                        <input id="certification-photo" class="form-control mblrc-input" type="file" name="photo" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required>
                        <small class="form-text text-muted mt-1">Maximum 5 MiB, 100&times;100 minimum, 8000&times;8000 maximum, and 16 million pixels.</small>
                    </div>
                    <div class="col-md-3">
                        <button class="btn w-100 font-weight-bold" type="submit" style="background: #312e81; color: #ffffff; border-radius: 10px; padding: 0.65rem 1rem;">
                            <i class="mdi mdi-upload mr-1"></i> Upload & Select
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Draft Form --}}
    <form method="POST" action="{{ route('japic.certifications.draft.update', $processing) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="revision" value="{{ $processing->draft?->revision ?? 0 }}">
        <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">

        {{-- 2. Official Control Information --}}
        <div class="mblrc-form-card">
            <div class="mblrc-form-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="mblrc-form-header-icon" style="background: #fffbeb; color: #d97706;">
                        <i class="mdi mdi-numeric"></i>
                    </div>
                    <div>
                        <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Official Control Information</h4>
                        <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Control tracking number and date of issuance</p>
                    </div>
                </div>
            </div>
            <div class="mblrc-form-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="control-number" class="mblrc-label">Control Number</label>
                        <input id="control-number" class="form-control mblrc-input @if(filled($processing->control_number)) mblrc-input-readonly @endif" name="control_number" maxlength="100" required @if(filled($processing->control_number)) readonly @endif value="{{ old('control_number', $processing->control_number) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="date-issued" class="mblrc-label">Date Issued</label>
                        <input id="date-issued" class="form-control mblrc-input" type="date" name="certificate[date_issued]" value="{{ old('certificate.date_issued', $certificate['date_issued'] ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Structured Narrative --}}
        <div class="mblrc-form-card">
            <div class="mblrc-form-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="mblrc-form-header-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="mdi mdi-file-document-outline"></i>
                    </div>
                    <div>
                        <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Structured Certification Narrative</h4>
                        <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Standardized intelligence certification statement</p>
                    </div>
                </div>
            </div>
            <div class="mblrc-form-card-body">
                <p class="fixed-narrative">
                    <strong>THIS IS TO CERTIFY THAT</strong>
                    <input aria-label="FR name" name="certificate[narrative_values][fr_name]" maxlength="255" required value="{{ old('certificate.narrative_values.fr_name', $narrative['fr_name'] ?? '') }}">, residing in
                    <input aria-label="Residence" name="certificate[narrative_values][residence]" maxlength="1000" required value="{{ old('certificate.narrative_values.residence', $narrative['residence'] ?? '') }}">, is a former
                    <input aria-label="Former organization or category" name="certificate[narrative_values][former_organization_or_category]" maxlength="500" required value="{{ old('certificate.narrative_values.former_organization_or_category', $narrative['former_organization_or_category'] ?? '') }}">, operating in the area/s of
                    <input aria-label="Areas of operation" name="certificate[narrative_values][areas_of_operation]" maxlength="2000" required value="{{ old('certificate.narrative_values.areas_of_operation', $narrative['areas_of_operation'] ?? '') }}">. {{ $wording['affiliation_subject'] }} started {{ $wording['possessive'] }} affiliation with the
                    <input aria-label="Affiliated organization" name="certificate[narrative_values][affiliated_organization]" maxlength="500" required value="{{ old('certificate.narrative_values.affiliated_organization', $narrative['affiliated_organization'] ?? '') }}">@if(filled($affiliationPeriod)) during {{ $affiliationPeriod }}@endif and surrendered to
                    <input aria-label="Office or organization surrendered to" name="certificate[narrative_values][surrendered_to]" maxlength="500" required value="{{ old('certificate.narrative_values.surrendered_to', $narrative['surrendered_to'] ?? '') }}"> on
                    <input aria-label="Date surrendered" type="date" name="certificate[narrative_values][surrendered_on]" required value="{{ old('certificate.narrative_values.surrendered_on', $narrative['surrendered_on'] ?? '') }}"> at
                    <input aria-label="Place surrendered" name="certificate[narrative_values][surrendered_at]" maxlength="1000" required value="{{ old('certificate.narrative_values.surrendered_at', $narrative['surrendered_at'] ?? '') }}">.
                </p>
                <div class="p-3 rounded-3 mt-2" style="background: #f1f5f9; font-style: italic; color: #475569; font-size: 0.9rem;">
                    {{ $wording['purpose'] }}
                </div>
            </div>
        </div>

        {{-- 4. Signatories --}}
        @foreach(['prepared_by' => ['Prepared By', $prepared, 'mdi-account-edit', '#eef2ff', '#312e81'], 'attested_by' => ['Attested By', $attested, 'mdi-account-check', '#fffbeb', '#d97706']] as $section => [$heading, $rows, $icon, $bg, $color])
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: {{ $bg }}; color: {{ $color }};">
                            <i class="mdi {{ $icon }}"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">{{ $heading }}</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Designated military and police signatories</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm add-personnel" data-section="{{ $section }}" style="background: #eef2ff; color: #312e81; font-weight: 750; border-radius: 8px; border: 1px solid #c7d2fe;">
                        <i class="mdi mdi-plus mr-1"></i> Add Person
                    </button>
                </div>
                <div class="mblrc-form-card-body">
                    <div id="{{ $section }}-rows" data-personnel-section="{{ $section }}">
                        @foreach($rows as $index => $row)
                            <div class="personnel-row" data-personnel-row>
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-7">
                                        <label class="mblrc-label">Full Name</label>
                                        <input class="form-control mblrc-input" name="certificate[{{ $section }}][{{ $index }}][full_name]" maxlength="255" required value="{{ $row['full_name'] ?? '' }}" placeholder="Enter full name...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="mblrc-label">Rank / Designation</label>
                                        <input class="form-control mblrc-input" name="certificate[{{ $section }}][{{ $index }}][rank]" maxlength="100" required value="{{ $row['rank'] ?? '' }}" placeholder="Enter rank...">
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end justify-content-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-personnel" aria-label="Remove person" style="border-radius: 8px; padding: 0.45rem 0.65rem;" title="Remove this person">
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        @if($processing->delayed)
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-body">
                    <label for="delay-reason" class="mblrc-label text-danger font-weight-bold">
                        <i class="mdi mdi-alert mr-1"></i> Justification / Delay Reason
                    </label>
                    <textarea id="delay-reason" class="form-control mblrc-input" name="delay_reason" maxlength="2000" required placeholder="State the reason for delay...">{{ old('delay_reason') }}</textarea>
                </div>
            </div>
        @endif

        {{-- Action Bar --}}
        <div class="mblrc-action-bar mb-4">
            <a href="{{ route('japic.certifications.show', $processing) }}" class="btn btn-light" style="border-radius: 10px; font-weight: 700; border: 1px solid #cbd5e1; padding: 0.65rem 1.25rem;">
                Cancel
            </a>
            <button class="btn" type="submit" style="background: #312e81; color: #ffffff; font-weight: 750; border-radius: 10px; padding: 0.65rem 1.65rem; box-shadow: 0 4px 12px rgba(49, 46, 129, 0.25);">
                <i class="mdi mdi-content-save mr-1"></i> Save Encrypted Draft
            </button>
        </div>
    </form>
</div>

<template id="personnel-template">
    <div class="personnel-row" data-personnel-row>
        <div class="row g-3 align-items-center">
            <div class="col-md-7">
                <label class="mblrc-label">Full Name</label>
                <input class="form-control mblrc-input" data-field="full_name" maxlength="255" required placeholder="Enter full name...">
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Rank / Designation</label>
                <input class="form-control mblrc-input" data-field="rank" maxlength="100" required placeholder="Enter rank...">
            </div>
            <div class="col-md-1 d-flex align-items-end justify-content-end">
                <button type="button" class="btn btn-outline-danger btn-sm remove-personnel" aria-label="Remove person" style="border-radius: 8px; padding: 0.45rem 0.65rem;" title="Remove this person">
                    <i class="mdi mdi-delete"></i>
                </button>
            </div>
        </div>
    </div>
</template>

@push('scripts')
<script>
(() => {
    const maximum = {{ $maxPersonnelRows }};
    const reindex = section => section.querySelectorAll('[data-personnel-row]').forEach((row, index) => row.querySelectorAll('[data-field], input[name]').forEach(input => {
        const field = input.dataset.field || input.name.match(/\[([^\]]+)]$/)?.[1];
        input.name = `certificate[${section.dataset.personnelSection}][${index}][${field}]`;
    }));
    document.querySelectorAll('[data-personnel-section]').forEach(section => {
        section.addEventListener('click', event => {
            const removeBtn = event.target.closest('.remove-personnel');
            if (!removeBtn) return;
            if (section.querySelectorAll('[data-personnel-row]').length > 1) {
                removeBtn.closest('[data-personnel-row]').remove();
                reindex(section);
            }
        });
        reindex(section);
    });
    document.querySelectorAll('.add-personnel').forEach(button => button.addEventListener('click', () => {
        const section = document.querySelector(`[data-personnel-section="${button.dataset.section}"]`);
        if (section.querySelectorAll('[data-personnel-row]').length >= maximum) return;
        section.append(document.getElementById('personnel-template').content.cloneNode(true));
        reindex(section);
    }));
})();
</script>
@endpush
@endsection

