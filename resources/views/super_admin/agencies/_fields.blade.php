<div class="row g-3">
    {{-- Acronym --}}
    <div class="col-md-5">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Agency Acronym <span class="text-danger">*</span></label>
        <input name="acronym" value="{{ old('acronym') }}" required class="form-control modern-input" placeholder="e.g. DILG, TESDA, DOLE">
        <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">Must be unique regardless of capitalization or surrounding spaces.</small>
        @error('acronym') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>

    {{-- Official Name --}}
    <div class="col-md-7">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Official Agency Name <span class="text-danger">*</span></label>
        <input name="name" value="{{ old('name') }}" required class="form-control modern-input" placeholder="e.g. Department of Labor and Employment">
        <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">Must be unique regardless of capitalization or surrounding spaces.</small>
        @error('name') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>

    {{-- Agency Logo --}}
    <div class="col-12">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Agency Logo <span class="text-muted fw-normal" style="font-size: 0.75rem;">(Optional)</span></label>
        <div class="d-flex align-items-start gap-3">
            <div data-agency-logo-preview class="sa-agency-logo-box" style="width: 58px; height: 58px;">
                @if (old('profile') && in_array(old('profile'), $agencyLogos, true))
                    <img src="{{ asset('assets/logoAgency/'.old('profile')) }}" alt="Selected agency logo" class="sa-agency-logo-img">
                @else
                    <i class="mdi mdi-bank text-muted" style="font-size: 1.35rem;"></i>
                @endif
            </div>
            <div class="flex-grow-1">
                <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Select Existing Logo</label>
            </div>
        </div>
        <input type="hidden" name="profile" value="{{ old('profile') }}" data-agency-logo-value>
        <div class="sa-logo-picker mt-2" data-agency-logo-picker role="group" aria-label="Select an existing agency logo">
            @forelse ($agencyLogos as $logo)
                <button type="button"
                        class="sa-logo-option {{ old('profile') === $logo ? 'is-selected' : '' }}"
                        data-agency-logo-option
                        data-logo="{{ $logo }}"
                        aria-pressed="{{ old('profile') === $logo ? 'true' : 'false' }}"
                        aria-label="Select {{ $logo }}">
                    <span class="sa-logo-option-image">
                        <img src="{{ asset('assets/logoAgency/'.$logo) }}"
                             alt="{{ pathinfo($logo, PATHINFO_FILENAME) }} logo"
                             onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');">
                        <span class="sa-logo-option-fallback d-none" aria-hidden="true"><i class="mdi mdi-bank"></i></span>
                    </span>
                    <span class="sa-logo-option-name" title="{{ $logo }}">{{ $logo }}</span>
                    <span class="sa-logo-option-check" aria-hidden="true"><i class="mdi mdi-check-circle"></i></span>
                </button>
            @empty
                <p class="text-muted small mb-0 p-3">No existing agency logos are available. Upload a new logo below.</p>
            @endforelse
        </div>
        @error('profile') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror

        <div class="mt-3">
            <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Or Upload New Logo</label>
            <input name="profile_upload" data-agency-logo-upload type="file" accept="image/jpeg,image/png,image/webp" class="form-control modern-input">
            <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">JPG, PNG, or WEBP up to 5MB. Uploading a file clears the existing-logo selection.</small>
            @error('profile_upload') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
