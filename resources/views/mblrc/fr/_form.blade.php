@php
    $fr = $fr ?? null;
    $val = fn ($field, $default = '') => old($field, $fr->$field ?? $default);
    $selBarangays = $barangays ?? collect();
@endphp

{{-- 1. Personal Profile Information Card --}}
<div class="mblrc-form-card">
    <div class="mblrc-form-card-header">
        <div class="d-flex align-items-center" style="gap: 0.85rem;">
            <div class="mblrc-form-header-icon" style="background: #eef2ff; color: #4338ca;">
                <i class="mdi mdi-account-circle"></i>
            </div>
            <div>
                <h4 class="mb-0 font-weight-bold" style="font-size: 0.95rem; color: #0f172a; letter-spacing: -0.01em;">Personal Profile Information</h4>
                <p class="mb-0 text-muted small">Full legal name, demographics, and identification details.</p>
            </div>
        </div>
    </div>
    <div class="mblrc-form-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="mblrc-label">First Name <span class="text-danger font-weight-bold">*</span></label>
                <input type="text" name="firstname" value="{{ $val('firstname') }}" placeholder="Enter First Name" required class="mblrc-input">
                @error('firstname') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Middle Name</label>
                <input type="text" name="middlename" value="{{ $val('middlename') }}" placeholder="Enter Middle Name" class="mblrc-input">
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Last Name <span class="text-danger font-weight-bold">*</span></label>
                <input type="text" name="lastname" value="{{ $val('lastname') }}" placeholder="Enter Last Name" required class="mblrc-input">
                @error('lastname') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Alias / Nickname</label>
                <input type="text" name="nickname" value="{{ $val('nickname') }}" placeholder="Enter Alias or Standing Name" class="mblrc-input">
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Suffix</label>
                <select name="suffix" class="mblrc-select">
                    <option value="">Select Suffix (Optional)</option>
                    @foreach (['Jr.', 'Sr.', 'II', 'III'] as $s)
                        <option value="{{ $s }}" @selected($val('suffix') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Gender</label>
                <select name="gender" class="mblrc-select">
                    <option value="">Select Gender</option>
                    @foreach (['Male', 'Female'] as $g)
                        <option value="{{ $g }}" @selected($val('gender') === $g)>{{ $g }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Age</label>
                <input type="number" name="age" min="0" max="120" value="{{ $val('age') }}" placeholder="Enter Age" class="mblrc-input">
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Civil Status</label>
                <select name="civil_status" class="mblrc-select">
                    <option value="">Select Civil Status</option>
                    @foreach (['Single', 'Married', 'Widowed', 'Separated'] as $c)
                        <option value="{{ $c }}" @selected($val('civil_status') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Birthday</label>
                <input type="date" name="birthdate" value="{{ $val('birthdate') ? \Illuminate\Support\Carbon::parse($val('birthdate'))->toDateString() : '' }}" class="mblrc-input">
            </div>
            <div class="col-md-6">
                <label class="mblrc-label">Contact Number</label>
                <input type="text" name="contact_num" value="{{ $val('contact_num') }}" placeholder="09XXXXXXXXX" class="mblrc-input">
            </div>
        </div>
    </div>
</div>

{{-- 2. Address & Geographic Location Card --}}
<div class="mblrc-form-card">
    <div class="mblrc-form-card-header">
        <div class="d-flex align-items-center" style="gap: 0.85rem;">
            <div class="mblrc-form-header-icon" style="background: #eff6ff; color: #2563eb;">
                <i class="mdi mdi-map-marker"></i>
            </div>
            <div>
                <h4 class="mb-0 font-weight-bold" style="font-size: 0.95rem; color: #0f172a; letter-spacing: -0.01em;">Address & Geographic Location</h4>
                <p class="mb-0 text-muted small">Official jurisdiction and current residential placement.</p>
            </div>
        </div>
    </div>
    <div class="mblrc-form-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="mblrc-label">Province</label>
                <input type="text" name="province" value="{{ $val('province', 'Davao del Sur') }}" readonly class="mblrc-input mblrc-input-readonly">
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Municipality / City <span class="text-danger font-weight-bold">*</span></label>
                <select name="municipality_id" required class="mblrc-select"
                        data-barangay-source="{{ route('mblrc.barangays') }}" data-barangay-target="#barangaySelect">
                    <option value="">Select Municipality / City</option>
                    @foreach ($municipalities as $m)
                        <option value="{{ $m->id }}" @selected((int) $val('municipality_id') === $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
                @error('municipality_id') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Barangay <span class="text-danger font-weight-bold">*</span></label>
                <select name="barangay_id" id="barangaySelect" required class="mblrc-select" data-selected="{{ $val('barangay_id') }}">
                    <option value="">Select Barangay</option>
                    @foreach ($selBarangays as $b)
                        <option value="{{ $b->id }}" @selected((int) $val('barangay_id') === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                @error('barangay_id') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Zip Code</label>
                <select name="zipcode" class="mblrc-select">
                    <option value="">Select Zip Code</option>
                    @foreach (range(8001, 8010) as $z)
                        <option value="{{ $z }}" @selected((string) $val('zipcode') === (string) $z)>{{ $z }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="mblrc-label">Current Residential Address</label>
                <input type="text" name="residential_address" value="{{ $val('residential_address') }}" placeholder="Street, Purok, House Number, or Landmark" class="mblrc-input">
            </div>
        </div>
    </div>
</div>

{{-- 3. Background & Surrender Information Card --}}
<div class="mblrc-form-card">
    <div class="mblrc-form-card-header">
        <div class="d-flex align-items-center" style="gap: 0.85rem;">
            <div class="mblrc-form-header-icon" style="background: #fffbeb; color: #d97706;">
                <i class="mdi mdi-history"></i>
            </div>
            <div>
                <h4 class="mb-0 font-weight-bold" style="font-size: 0.95rem; color: #0f172a; letter-spacing: -0.01em;">Background & Surrender Details</h4>
                <p class="mb-0 text-muted small">Surrender timeline, batch assignment, and reintegration status.</p>
            </div>
        </div>
    </div>
    <div class="mblrc-form-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="mblrc-label">Date of Surrender</label>
                <input type="date" name="surrender_date" value="{{ $val('surrender_date') ? \Illuminate\Support\Carbon::parse($val('surrender_date'))->toDateString() : '' }}" class="mblrc-input">
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Batch Year</label>
                <select name="batch_year" class="mblrc-select">
                    <option value="">Select Batch Year</option>
                    @foreach (['2026', '2025', '2024', '2023', '2022'] as $y)
                        <option value="{{ $y }}" @selected((string) $val('batch_year') === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Batch Section</label>
                <select name="batch_section" class="mblrc-select">
                    <option value="">Select Section</option>
                    @foreach (['1', '2', '3', '4'] as $b)
                        <option value="{{ $b }}" @selected((string) $val('batch_section') === $b)>Section {{ $b }}</option>
                    @endforeach
                </select>
            </div>
            @isset($statuses)
                <div class="col-md-4">
                    <label class="mblrc-label">Initial Status</label>
                    <select name="status" class="mblrc-select">
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected($val('status', 'Active') === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
            @endisset
            <div class="col-12">
                <label class="mblrc-label">Reason for Surrender</label>
                <textarea name="surrender_reason" rows="3" class="mblrc-input" style="height: auto;" placeholder="Provide context, surrendered unit, or specific reason for surrender...">{{ $val('surrender_reason') }}</textarea>
            </div>
        </div>
    </div>
</div>
