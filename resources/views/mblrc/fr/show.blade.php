@extends('layouts.skydash-v')
@section('title', $fr->full_name)
@section('heading', 'Former Rebel Profile')

@php
    $ps = $fr->programStatus;
    $softStatus = match ($fr->status) {
        'Active', 'Reintegrated', 'Completed' => 'badge-soft-shield',
        'On hold', 'Under Review', 'Pending', 'Suspended' => 'badge-soft-warning',
        'Inactive', 'Disengaged', 'Deceased' => 'badge-soft-danger',
        default => 'badge-soft-info',
    };
    $fullName = trim($fr->firstname.' '.$fr->lastname.' '.$fr->suffix);
    $batch = 'Batch '.($fr->batch_section ? $fr->batch_section.' - ' : '').($fr->batch_year ?: '—');
    $fullAddress = trim(($fr->residential_address ? $fr->residential_address.', ' : '').($fr->barangay?->name ? $fr->barangay->name.', ' : '').($fr->municipality?->name ?? ''));
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .fr-avatar { width: 72px; height: 72px; object-fit: cover; }
    .mblrc-profile-tab-nav {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.5rem;
        box-shadow: 0 1px 3px rgba(15,23,42,0.04);
        margin-bottom: 1.5rem;
    }
    .mblrc-profile-tab-nav .nav-pills .nav-link {
        border-radius: 10px;
        padding: 0.7rem 1.25rem;
        font-weight: 750;
        font-size: 0.8125rem;
        color: #64748b;
        letter-spacing: 0.03em;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }
    .mblrc-profile-tab-nav .nav-pills .nav-link:hover {
        color: #1e1b4b;
        background: #f8fafc;
    }
    .mblrc-profile-tab-nav .nav-pills .nav-link.active {
        background: #312e81;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(49, 46, 129, 0.25);
    }
    .info-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem 1.15rem;
        height: 100%;
        transition: all .2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .info-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(15,23,42,.05);
    }
    .info-card small.label-text {
        color: #64748b;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 0.35rem;
        display: block;
    }
    .icon-container {
        width: 36px;
        height: 36px;
        min-width: 36px;
        background: #eef2ff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        color: #4338ca;
        font-size: 1.2rem;
    }
    .icon-container.accent-amber { background: #fffbeb; color: #d97706; }
    .icon-container.accent-blue { background: #eff6ff; color: #2563eb; }
    .icon-container.accent-shield { background: #eef2ff; color: #312e81; }
    .icon-container.accent-purple { background: #f5f3ff; color: #7c3aed; }
    .form-control-static {
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0;
        font-size: 0.9375rem;
        line-height: 1.35;
        word-break: break-word;
    }
    .badge-soft-danger { color: #e11d48; background-color: #fff1f2; border: 1px solid #fecdd3; }
    .badge-soft-shield { color: #312e81; background-color: #eef2ff; border: 1px solid #c7d2fe; }
    .badge-soft-info { color: #0284c7; background-color: #f0f9ff; border: 1px solid #bae6fd; }
    .badge-soft-warning { color: #d97706; background-color: #fffbeb; border: 1px solid #fde68a; }
    #frLocationMap {
        height: 400px;
        width: 100%;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
    .item-card-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.9rem 1.15rem;
        transition: all 0.2s ease;
    }
    .item-card-row:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(15,23,42,0.05);
    }
</style>
@endpush

@section('content')
<div class="mblrc-dashboard-container" id="frProfile"
     data-fr-id="{{ $fr->id }}"
     data-program-status="{{ route('mblrc.fr.program-status.update', $fr) }}"
     data-location-geocode="{{ route('mblrc.fr.location.geocode', $fr) }}"
     data-location-save="{{ route('mblrc.fr.location.save', $fr) }}"
     data-location-history="{{ route('mblrc.fr.location.history', $fr) }}"
     data-skills-store="{{ route('mblrc.fr.skills.store', $fr) }}"
     data-skills-suggest="{{ route('mblrc.fr.skills.suggestions') }}"
     data-assistance-store="{{ route('mblrc.fr.assistance.store', $fr) }}"
     data-education-store="{{ route('mblrc.fr.education.update', $fr) }}"
     data-lat="{{ $fr->latitude }}" data-lng="{{ $fr->longitude }}"
     data-has-saved-location="{{ $fr->latitude !== null && $fr->longitude !== null ? '1' : '0' }}">

    {{-- Modern Hero Banner (SHIELD Brand Design) --}}
    <div class="mblrc-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1.25rem;">
            <div class="d-flex align-items-center" style="gap: 1.25rem;">
                <div class="rounded-circle overflow-hidden border border-3 border-white shadow-sm flex-shrink-0" style="width: 72px; height: 72px; background: #ffffff;">
                    <img src="{{ asset('assets/img/fr-profile.jpg') }}" alt="profile" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        MBLRC Profile &middot; {{ $fr->classified_id }}
                    </div>
                    <h1 class="mblrc-hero-title mb-1">{{ $fullName }}</h1>
                    <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                        <span class="badge" style="background: rgba(225, 29, 72, 0.25); color: #fecdd3; border: 1px solid rgba(225, 29, 72, 0.4); font-weight: 750; font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 9999px;">
                            <i class="mdi mdi-shield-account mr-1"></i> Former Rebel
                        </span>
                        <span class="badge {{ $softStatus }}" style="padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 750; font-size: 0.75rem;">
                            <i class="mdi mdi-check-circle-outline mr-1"></i> {{ $fr->status }}
                        </span>
                        @if ($fr->surrender_date)
                            <span class="badge" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.25); font-weight: 700; font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 9999px;">
                                <i class="mdi mdi-calendar mr-1"></i> Surrendered {{ $fr->surrender_date->format('M d, Y') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('mblrc.fr.edit', $fr) }}" class="btn" style="background: #ffffff; color: #312e81; font-weight: 800; font-size: 0.875rem; padding: 0.55rem 1.25rem; border-radius: 10px; border: none; box-shadow: 0 4px 14px rgba(0,0,0,0.18); transition: all 0.2s ease;">
                    <i class="mdi mdi-pencil mr-1"></i> Edit Profile
                </a>
                <a href="{{ route('mblrc.fr.index') }}" class="btn" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #ffffff; font-weight: 700; border-radius: 10px; padding: 0.55rem 1.15rem;">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="mblrc-profile-tab-nav">
        <ul class="nav nav-pills border-0" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab-profile" role="tab">
                    <i class="mdi mdi-account-circle"></i> Profile Information
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-reintegration" role="tab">
                    <i class="mdi mdi-school"></i> Reintegration Information
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-geotag" role="tab">
                    <i class="mdi mdi-map-marker-radius"></i> Geotag & Location
                </a>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        {{-- TAB 1: PROFILE INFORMATION --}}
        <div class="tab-pane fade show active" id="tab-profile" role="tabpanel">
            
            {{-- Card 1: Personal Demographic Details --}}
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #eef2ff; color: #312e81;">
                            <i class="mdi mdi-account-card-details"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Personal Profile Information</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Core demographic records and contact information</p>
                        </div>
                    </div>
                    <a href="{{ route('mblrc.fr.edit', $fr) }}" class="btn btn-sm" style="background: #eef2ff; color: #312e81; font-weight: 750; border-radius: 8px; padding: 0.4rem 0.9rem; border: 1px solid #c7d2fe;">
                        <i class="mdi mdi-pencil mr-1"></i> Edit Details
                    </a>
                </div>
                <div class="mblrc-form-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-card">
                                <small class="label-text">Full Legal Name</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-shield"><i class="mdi mdi-account-card-details"></i></div>
                                    <div class="form-control-static">{{ $fullName }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <small class="label-text">Alias / Nickname</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-shield"><i class="mdi mdi-tag-outline"></i></div>
                                    <div class="form-control-static">{{ $fr->nickname ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <small class="label-text">Contact Number</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-shield"><i class="mdi mdi-phone"></i></div>
                                    <div class="form-control-static">{{ $fr->contact_num ?: 'None' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <small class="label-text">Gender</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-blue"><i class="mdi mdi-gender-male-female"></i></div>
                                    <div class="form-control-static">{{ $fr->gender ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <small class="label-text">Civil Status</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-blue"><i class="mdi mdi-heart"></i></div>
                                    <div class="form-control-static">{{ $fr->civil_status ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <small class="label-text">Date of Birth</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-blue"><i class="mdi mdi-calendar"></i></div>
                                    <div class="form-control-static">{{ $fr->birthdate?->format('F d, Y') ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <small class="label-text">Age</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-blue"><i class="mdi mdi-cake-variant"></i></div>
                                    <div class="form-control-static">{{ $fr->age ? $fr->age.' yrs old' : '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 2: Address & Location Details --}}
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #fffbeb; color: #d97706;">
                            <i class="mdi mdi-map-marker"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Address & Location Details</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Municipality, barangay, and residential address</p>
                        </div>
                    </div>
                </div>
                <div class="mblrc-form-card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="info-card">
                                <small class="label-text">Municipality</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-amber"><i class="mdi mdi-city"></i></div>
                                    <div class="form-control-static">{{ $fr->municipality?->name ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-card">
                                <small class="label-text">Barangay</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-amber"><i class="mdi mdi-map-marker"></i></div>
                                    <div class="form-control-static">{{ $fr->barangay?->name ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-card">
                                <small class="label-text">Zip Code</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-amber"><i class="mdi mdi-numeric"></i></div>
                                    <div class="form-control-static">{{ $fr->zipcode ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-card">
                                <small class="label-text">Complete Residential Address</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-amber"><i class="mdi mdi-home"></i></div>
                                    <div class="form-control-static">{{ $fullAddress ?: 'No specific street address provided' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 3: Surrender Background --}}
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #eff6ff; color: #2563eb;">
                            <i class="mdi mdi-history"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Surrender Background</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Surrender timeline, batch assignment, and remarks</p>
                        </div>
                    </div>
                </div>
                <div class="mblrc-form-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-card">
                                <small class="label-text">Date of Surrender</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-blue"><i class="mdi mdi-calendar"></i></div>
                                    <div class="form-control-static">{{ $fr->surrender_date?->format('F d, Y') ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <small class="label-text">Batch Information</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-blue"><i class="mdi mdi-account-group"></i></div>
                                    <div class="form-control-static">{{ $batch }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-card">
                                <small class="label-text">Reason of Surrender / Narrative</small>
                                <div class="d-flex align-items-start">
                                    <div class="icon-container accent-blue mt-1"><i class="mdi mdi-file-document"></i></div>
                                    <div class="form-control-static" style="font-weight: 500; font-size: 0.875rem; line-height: 1.5;">{{ $fr->surrender_reason ?: 'No surrender narrative recorded.' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 4: 3-Months Program Status --}}
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #f5f3ff; color: #7c3aed;">
                            <i class="mdi mdi-clipboard-text"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">3-Months Program Status</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Reintegration track and completion milestone</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm" style="background-color: #312e81; color: #ffffff; font-weight: 750; border-radius: 8px; padding: 0.4rem 0.9rem;" data-bs-toggle="collapse" data-bs-target="#editStatusForm">
                        <i class="mdi mdi-pencil mr-1"></i> Update Status
                    </button>
                </div>
                <div class="mblrc-form-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-card">
                                <small class="label-text">Reintegration Status</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-purple"><i class="mdi mdi-check-circle-outline"></i></div>
                                    <div class="form-control-static">{{ $ps?->reintegration_status ?? 'Not-Started' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <small class="label-text">Date of Reintegration</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-purple"><i class="mdi mdi-calendar-check"></i></div>
                                    <div class="form-control-static">{{ $ps?->reintegration_date?->format('F d, Y') ?? 'Not set' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="collapse mt-3" id="editStatusForm">
                        <form data-program-form class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="mblrc-label">Reintegration Status</label>
                                    <select name="reintegration_status" class="mblrc-select">
                                        @foreach (['Not-Started', 'On-going', 'Completed'] as $s)
                                            <option value="{{ $s }}" @selected(($ps?->reintegration_status ?? 'Not-Started') === $s)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="mblrc-label">Date of Reintegration</label>
                                    <input name="reintegration_date" type="date" value="{{ $ps?->reintegration_date?->toDateString() }}" class="mblrc-input">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn w-100" style="background: #312e81; color: #ffffff; font-weight: 750; border-radius: 10px; padding: 0.65rem 1rem;">Update</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 2: REINTEGRATION INFORMATION --}}
        <div class="tab-pane fade" id="tab-reintegration" role="tabpanel">

            {{-- Card 1: Education and Work --}}
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #eef2ff; color: #312e81;">
                            <i class="mdi mdi-school"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Education & Employment</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Educational attainment and current occupation</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm" style="background: #eef2ff; color: #312e81; font-weight: 750; border-radius: 8px; padding: 0.4rem 0.9rem; border: 1px solid #c7d2fe;" data-bs-toggle="collapse" data-bs-target="#eduForm">
                        <i class="mdi mdi-pencil mr-1"></i> Edit Info
                    </button>
                </div>
                <div class="mblrc-form-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-card">
                                <small class="label-text">Educational Attainment</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-shield"><i class="mdi mdi-school"></i></div>
                                    <div class="form-control-static">{{ $education?->educational_attainment ?: 'Not Specified' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <small class="label-text">Current Work / Profession</small>
                                <div class="d-flex align-items-center">
                                    <div class="icon-container accent-shield"><i class="mdi mdi-briefcase"></i></div>
                                    <div class="form-control-static">{{ $education?->occupation ?: ($fr->occupation ?: 'Not specified') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="collapse mt-3" id="eduForm">
                        <form data-education-form class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="mblrc-label">Educational Level</label>
                                    <select name="educational_attainment" class="mblrc-select">
                                        <option value="">Select Educational Level</option>
                                        @foreach (['Elementary Level','Elementary Graduate','High School Level','High School Graduate','College Level','College Graduate','Post Graduate','Vocational'] as $lvl)
                                            <option value="{{ $lvl }}" @selected($education?->educational_attainment === $lvl)>{{ $lvl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="mblrc-label">Current Work/Profession</label>
                                    <input name="occupation" placeholder="Enter current work/profession" class="mblrc-input" value="{{ $education?->occupation ?? $fr->occupation }}">
                                </div>
                                <div class="col-12 text-end">
                                    <button class="btn" style="background: #312e81; color: #ffffff; font-weight: 750; border-radius: 10px; padding: 0.6rem 1.4rem;">Save Information</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Card 2: Skill/Works Enhancement --}}
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #eff6ff; color: #2563eb;">
                            <i class="mdi mdi-wrench"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Skills & Vocational Enhancement</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Technical proficiencies and specialized training</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm" style="background: #2563eb; color: #ffffff; font-weight: 750; border-radius: 8px; padding: 0.4rem 0.9rem;" data-bs-toggle="collapse" data-bs-target="#skillForm">
                        <i class="mdi mdi-plus mr-1"></i> Add Skill
                    </button>
                </div>
                <div class="mblrc-form-card-body">
                    <ul data-skills-list class="list-unstyled mb-0 d-flex flex-column gap-2">
                        @foreach ($fr->skills as $skill)
                            <li class="item-card-row d-flex align-items-center justify-content-between" data-skill-id="{{ $skill->id }}">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-container accent-blue mb-0"><i class="mdi mdi-wrench"></i></div>
                                    <div>
                                        <h6 class="mb-1 font-weight-bold" style="color: #0f172a; font-size: 0.9375rem;">{{ $skill->skill_name }}</h6>
                                        <span class="badge" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-weight: 700; font-size: 0.75rem;">
                                            <i class="mdi mdi-star mr-1"></i> {{ $skill->proficiency_level }}
                                        </span>
                                    </div>
                                </div>
                                <button type="button" data-skill-delete="{{ route('mblrc.fr.skills.destroy', $skill) }}" class="btn btn-outline-danger btn-sm" style="border-radius: 8px; padding: 0.35rem 0.65rem;" title="Delete skill">
                                    <i class="mdi mdi-delete"></i>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    @if ($fr->skills->isEmpty())
                        <div class="d-flex align-items-center p-3 rounded-3 text-muted" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                            <i class="mdi mdi-information-outline me-2" style="font-size: 1.25rem; color: #64748b;"></i>
                            <span>No skills or vocational records added yet.</span>
                        </div>
                    @endif
                    <div class="collapse mt-3" id="skillForm">
                        <form data-skill-form class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="mblrc-label">Skill Name</label>
                                    <input name="skill_name" list="skillSuggestions" placeholder="e.g. Carpentry, Welding, Farming" class="mblrc-input" required>
                                    <datalist id="skillSuggestions"></datalist>
                                </div>
                                <div class="col-md-4">
                                    <label class="mblrc-label">Proficiency Level</label>
                                    <select name="proficiency_level" class="mblrc-select">
                                        @foreach (['Beginner', 'Intermediate', 'Advanced'] as $p)
                                            <option value="{{ $p }}">{{ $p }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button class="btn w-100" style="background: #2563eb; color: #ffffff; font-weight: 750; border-radius: 10px; padding: 0.65rem 1rem;">Add</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Card 3: Government Assistance Received --}}
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #fffbeb; color: #d97706;">
                            <i class="mdi mdi-gift-outline"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Government Assistance Received</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Aid, livelihood packages, and social assistance granted</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm" style="background: #d97706; color: #ffffff; font-weight: 750; border-radius: 8px; padding: 0.4rem 0.9rem;" data-bs-toggle="collapse" data-bs-target="#assistForm">
                        <i class="mdi mdi-plus mr-1"></i> Add Assistance
                    </button>
                </div>
                <div class="mblrc-form-card-body">
                    <ul data-assistance-list class="list-unstyled mb-0 d-flex flex-column gap-2">
                        @foreach ($fr->assistances as $a)
                            <li class="item-card-row d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-container accent-amber mb-0"><i class="mdi mdi-gift-outline"></i></div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <h6 class="mb-0 font-weight-bold" style="color: #0f172a; font-size: 0.9375rem;">{{ $a->assistance_type }}</h6>
                                            <span class="badge badge-soft-info" style="font-size: 0.75rem; border-radius: 6px; padding: 0.2rem 0.55rem;">{{ $a->status }}</span>
                                        </div>
                                        <small class="text-muted"><i class="mdi mdi-calendar mr-1"></i>{{ $a->date_received?->format('F d, Y') ?? 'Date unspecified' }}</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($a->certificate_file)
                                        <a href="{{ Storage::url($a->certificate_file) }}" target="_blank" class="btn btn-sm" style="background: #eef2ff; color: #312e81; border: 1px solid #c7d2fe; font-weight: 700; border-radius: 8px; padding: 0.35rem 0.75rem;">
                                            <i class="mdi mdi-file-document mr-1"></i> Certificate
                                        </a>
                                    @endif
                                    <button type="button" data-assistance-delete="{{ route('mblrc.fr.assistance.destroy', $a) }}" class="btn btn-outline-danger btn-sm" style="border-radius: 8px; padding: 0.35rem 0.65rem;" title="Delete record">
                                        <i class="mdi mdi-delete"></i>
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if ($fr->assistances->isEmpty())
                        <div class="d-flex align-items-center p-3 rounded-3 text-muted" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                            <i class="mdi mdi-information-outline me-2" style="font-size: 1.25rem; color: #64748b;"></i>
                            <span>No government assistance records registered yet.</span>
                        </div>
                    @endif
                    <div class="collapse mt-3" id="assistForm">
                        <form data-assistance-form class="p-3 rounded-3" enctype="multipart/form-data" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="mblrc-label">Assistance Type</label>
                                    <input name="assistance_type" placeholder="e.g. Livelihood Grant, Medical Assistance" class="mblrc-input" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="mblrc-label">Date Received</label>
                                    <input name="date_received" type="date" class="mblrc-input">
                                </div>
                                <div class="col-md-3">
                                    <label class="mblrc-label">Status</label>
                                    <select name="status" class="mblrc-select">
                                        @foreach (['Pending', 'In Progress', 'Completed'] as $s)
                                            <option value="{{ $s }}">{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="mblrc-label">Supporting Document / Certificate (optional)</label>
                                    <input name="certificate" type="file" accept=".jpg,.jpeg,.png,.gif,.pdf" class="mblrc-input">
                                </div>
                                <div class="col-12 text-end">
                                    <button class="btn" style="background: #d97706; color: #ffffff; font-weight: 750; border-radius: 10px; padding: 0.6rem 1.4rem;">Add Assistance Record</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 3: GEOTAG & LOCATION --}}
        <div class="tab-pane fade" id="tab-geotag" role="tabpanel">
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #eef2ff; color: #312e81;">
                            <i class="mdi mdi-map-marker-radius"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Placement Geotag & Location</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Interactive map coordinates and placement history</p>
                        </div>
                    </div>
                    <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 700; border-radius: 8px; padding: 0.4rem 0.8rem; border: 1px solid #c7d2fe;">
                        <i class="mdi mdi-map-search mr-1"></i> Enter the placement address to locate it on the map
                    </span>
                </div>
                <div class="mblrc-form-card-body">
                    <form data-location-form class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="mblrc-label">Placement Address</label>
                                <input name="placement_address" value="{{ $fr->placement_address }}" placeholder="Enter placement address" class="mblrc-input" required>
                            </div>
                            <div class="col-md-5">
                                <label class="mblrc-label">Landmark <span class="text-muted font-weight-normal">(optional)</span></label>
                                <input name="landmark" value="" placeholder="Near New Opon Elementary School" class="mblrc-input">
                            </div>
                            <div class="col-12">
                                <div data-location-status class="small text-muted" role="status" aria-live="polite">
                                    @if ($fr->latitude !== null && $fr->longitude !== null)
                                        Saved location: {{ $fr->placement_address ?: 'Coordinates available' }}
                                    @else
                                        Locating the saved residential address&hellip;
                                    @endif
                                </div>
                            </div>
                            <input name="latitude" type="hidden" value="{{ $fr->latitude }}">
                            <input name="longitude" type="hidden" value="{{ $fr->longitude }}">
                            <div class="col-12">
                                <div id="frLocationMap"></div>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" data-location-save-button class="btn" style="background: #312e81; color: #ffffff; font-weight: 750; border-radius: 10px; padding: 0.65rem 1.4rem;">
                                    <i class="mdi mdi-content-save mr-1"></i> Save Location
                                </button>
                            </div>
                        </div>
                    </form>
                    <div data-location-history class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.getElementById('frProfile');
    if (!root) return;

    function token() {
        return document.querySelector('meta[name="csrf-token"]')?.content;
    }

    async function postJson(url, body, method = 'POST') {
        const isForm = body instanceof FormData;
        const res = await fetch(url, {
            method,
            headers: {
                'X-CSRF-TOKEN': token(),
                'Accept': 'application/json',
                ...(isForm ? {} : { 'Content-Type': 'application/json' }),
            },
            body: isForm ? body : JSON.stringify(body),
        });
        return res.json().catch(() => ({}));
    }

    // Program status
    root.querySelector('[data-program-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const r = await postJson(root.dataset.programStatus, {
            reintegration_status: f.reintegration_status.value,
            reintegration_date: f.reintegration_date.value || null,
        }, 'PUT');
        if (r.success) location.reload();
    });

    initAssistance(root);
    initLocationHistory(root);

    // Education / work
    root.querySelector('[data-education-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const r = await postJson(root.dataset.educationStore, {
            educational_attainment: f.educational_attainment.value,
            occupation: f.occupation.value,
        });
        if (r.success) location.reload();
    });

    function initLocationHistory(root) {
        fetch(root.dataset.locationHistory, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((rows) => {
                const box = root.querySelector('[data-location-history]');
                if (!box) return;
                box.innerHTML = rows.length
                    ? '<div class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;"><h6 class="font-weight-bold text-muted mb-2" style="font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.05em;">Placement History</h6>' + rows.map((h) =>
                        `<div class="d-flex align-items-center gap-2 mb-1" style="font-size: 0.875rem;"><i class="mdi mdi-map-marker text-primary"></i> <span>${h.placement_address ?? ''}</span> <span class="text-muted small">(${h.updated_by ?? ''})</span></div>`).join('') + '</div>'
                    : '';
            });
    }

    function initSkills(root) {
        const list = root.querySelector('[data-skills-list]');

        // suggestions
        fetch(root.dataset.skillsSuggest, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((rows) => {
                const dl = document.getElementById('skillSuggestions');
                if (dl) dl.innerHTML = rows.map((s) => `<option value="${s}">`).join('');
            });

        root.querySelector('[data-skill-form]')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const f = e.target;
            const r = await postJson(root.dataset.skillsStore, {
                skill_name: f.skill_name.value,
                proficiency_level: f.proficiency_level.value,
            });
            if (r.success) location.reload();
        });

        list?.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-skill-delete]');
            if (!btn) return;
            const r = await postJson(btn.dataset.skillDelete, {}, 'DELETE');
            if (r.success) btn.closest('[data-skill-id]').remove();
        });
    }

    function initAssistance(root) {
        const list = root.querySelector('[data-assistance-list]');

        root.querySelector('[data-assistance-form]')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const r = await postJson(root.dataset.assistanceStore, new FormData(e.target));
            if (r.success) location.reload();
        });

        list?.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-assistance-delete]');
            if (!btn) return;
            const r = await postJson(btn.dataset.assistanceDelete, {}, 'DELETE');
            if (r.success) location.reload();
        });
    }
})();
</script>
@endpush
