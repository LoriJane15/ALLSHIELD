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

    {{-- Profile / Description --}}
    <div class="col-12">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Logo Filename / Profile Identifier <span class="text-muted fw-normal" style="font-size: 0.75rem;">(Optional)</span></label>
        <input name="profile" value="{{ old('profile') }}" class="form-control modern-input" placeholder="e.g. dilg.png">
        <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">Place logo image file in <code>public/assets/logoAgency/</code> directory.</small>
        @error('profile') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>
</div>
