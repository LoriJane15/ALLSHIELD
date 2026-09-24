<div class="row g-3">
    {{-- Full Name --}}
    <div class="col-md-6">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Full Name <span class="text-danger">*</span></label>
        <input name="name" value="{{ old('name') }}" required class="form-control modern-input" placeholder="e.g. Maria Santos">
        @error('name') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>

    {{-- Username --}}
    <div class="col-md-6">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Username <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 10px 0 0 10px; font-size: 0.85rem; border: 1.5px solid #e2e8f0; border-right: none;">@</span>
            <input name="username" value="{{ old('username') }}" required class="form-control modern-input border-start-0" style="border-radius: 0 10px 10px 0 !important;" placeholder="maria_santos">
        </div>
        @error('username') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>

    {{-- Password --}}
    <div class="col-md-6">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">
            Password 
            @if ($isEdit)
                <span class="text-muted fw-normal" style="font-size: 0.75rem;">(Leave blank to keep current)</span>
            @else
                <span class="text-danger">*</span>
            @endif
        </label>
        <div class="position-relative">
            <input name="password" type="password" {{ $isEdit ? '' : 'required' }} class="form-control modern-input sa-password-input" autocomplete="new-password" placeholder="{{ $isEdit ? '••••••••' : 'Enter password' }}" style="padding-right: 2.5rem !important;">
            <button type="button" class="sa-password-btn" title="Toggle password visibility" onclick="togglePasswordVisibility(this)" tabindex="-1">
                <i class="mdi mdi-eye-outline"></i>
            </button>
        </div>
        <p class="mt-1 text-muted small mb-0">{{ $isEdit ? 'Confirmation is required when changing the password.' : 'The password must meet the system password rule and match its confirmation.' }}</p>
        @error('password') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>

    {{-- Password Confirmation --}}
    <div class="col-md-6">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">
            Confirm Password
            @if ($isEdit)
                <span class="text-muted fw-normal" style="font-size: 0.75rem;">(Required when changing password)</span>
            @else
                <span class="text-danger">*</span>
            @endif
        </label>
        <input name="password_confirmation" type="password" {{ $isEdit ? '' : 'required' }} class="form-control modern-input" autocomplete="new-password" placeholder="Confirm password">
    </div>

    {{-- Assigned Role --}}
    <div class="col-md-6">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Role <span class="text-danger">*</span></label>
        <select name="role" required class="form-select modern-input">
            @foreach (config('shield.roles') as $key => $meta)
                <option value="{{ $key }}" @selected(old('role') === $key)>{{ $meta['label'] }}</option>
            @endforeach
        </select>
        @error('role') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>

    @if ($isEdit)
        {{-- Account lifecycle --}}
        <div class="col-12">
            <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Account Status <span class="text-danger">*</span></label>
            <select name="is_active" required class="form-select modern-input">
                <option value="1" @selected((string) old('is_active', '1') === '1')>Active</option>
                <option value="0" @selected((string) old('is_active') === '0')>Inactive</option>
            </select>
            <p class="mt-1 text-muted small mb-0">Activate an account to allow sign-in. Deactivate it to block sign-in while preserving its records.</p>
            @error('is_active') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
        </div>
    @endif

    {{-- LGU only (Municipality) --}}
    <div data-role-field="lgu" class="col-12 d-none">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Assigned Municipality <span class="text-danger">*</span></label>
        <select name="municipality_id" class="form-select modern-input">
            <option value="">Select Municipality...</option>
            @foreach ($municipalities as $m)
                <option value="{{ $m->id }}" @selected((string) old('municipality_id') === (string) $m->id)>{{ $m->name }} ({{ $m->kind }})</option>
            @endforeach
        </select>
        <p class="mt-1 text-muted small mb-0">A valid municipality is required for LGU accounts.</p>
        @error('municipality_id') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>

    {{-- Gov Agency only (Agency) --}}
    <div data-role-field="gov_agency" class="col-12 d-none">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Assigned Government Agency <span class="text-danger">*</span></label>
        <select name="gov_agency_id" class="form-select modern-input">
            <option value="">Select Government Agency...</option>
            @foreach ($agencies as $a)
                <option value="{{ $a->id }}" @selected((string) old('gov_agency_id') === (string) $a->id)>{{ $a->acronym }} — {{ $a->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-muted small mb-0">A valid government agency is required for Government Agency accounts.</p>
        @error('gov_agency_id') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>

    {{-- Avatar / Logo Upload --}}
    <div class="col-12">
        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.82rem;">Profile Image or Seal <span class="text-muted fw-normal" style="font-size: 0.75rem;">(Optional)</span></label>
        <div class="p-3 rounded-3" style="background: #f8fafc; border: 1.5px dashed #cbd5e1;">
            <div class="d-flex align-items-center gap-3">
                <div class="sa-preview-box" style="width: 44px; height: 44px; border-radius: 10px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                    <i class="mdi mdi-image-outline text-muted fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold text-dark" style="font-size: 0.84rem;">Choose account image</div>
                            <small class="text-muted" style="font-size: 0.74rem;">PNG, JPG, or WEBP up to 2MB</small>
                        </div>
                        <label class="btn btn-sm btn-white border shadow-sm mb-0 px-3 py-1 fw-semibold text-primary" style="cursor: pointer; border-radius: 8px; font-size: 0.8rem; background: #ffffff;">
                            <i class="mdi mdi-upload me-1"></i> Browse File
                            <input name="logo" type="file" accept="image/*" class="d-none" onchange="handleFileChange(this)">
                        </label>
                    </div>
                    <div class="sa-file-name-display mt-1 text-primary fw-medium small" style="display: none;"></div>
                </div>
            </div>
        </div>
        @error('logo') <p class="mt-1 text-danger small mb-0">{{ $message }}</p> @enderror
    </div>
</div>
